<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ResponsiblePerson;
use App\Models\StudentReferenceEntry;
use App\Models\Student;
use App\Models\Role;
use App\Models\User;
use App\Models\Teacher;
use App\Services\AuditService;
use App\Services\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PeopleController extends Controller
{
    public function teachers(Request $request): View
    {
        return view('people.teachers', [
            'teachers' => Teacher::query()
                ->when($request->filled('search'), fn ($query) => $query
                    ->whereInsensitiveLike('first_name', $request->search)
                    ->orWhereInsensitiveLike('last_name', $request->search)
                    ->orWhereInsensitiveLike('employee_number', $request->search))
                ->orderBy('first_name')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function storeTeacher(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:teachers,employee_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        Teacher::create($data + ['is_active' => true]);

        return back()->with('success', 'Teacher added.');
    }

    public function showTeacher(Teacher $teacher, Request $request): View
    {
        $relations = ['assignments.academicYear', 'assignments.subject', 'assignments.grade', 'assignments.section', 'timetableEntries.subject', 'timetableEntries.grade', 'timetableEntries.section', 'attendances', 'substitutions.entry.subject'];
        if ($request->user()->hasPermission('medical.view')) {
            $relations[] = 'medicalRecords';
        }
        $teacher->load($relations);

        return view('people.teacher-show', [
            'teacher' => $teacher,
            'canViewMedical' => $request->user()->hasPermission('medical.view'),
        ]);
    }

    public function responsible(Request $request): View
    {
        $branchId = $this->selectedFamilyBranchId($request);
        $branches = app(BranchContext::class);

        return view('people.responsible', [
            'people' => ResponsiblePerson::query()
                ->with(['teacher', 'students' => fn ($students) => $students->withoutGlobalScopes()->where('students.branch_id', $branchId)])
                ->where('branch_id', $branchId)
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($matches) => $matches
                    ->whereInsensitiveLike('full_name', $request->search)
                    ->orWhereInsensitiveLike('phone', $request->search)
                    ->orWhereInsensitiveLike('parent_code', $request->search)))
                ->orderBy('full_name')
                ->paginate(15)
                ->withQueryString(),
            'teachers' => Teacher::where('is_active', true)->orderBy('first_name')->get(),
            'selectedStudents' => $this->studentsForPicker((array) old('student_ids', []), $branchId),
            'referenceOptions' => $this->referenceOptions(),
            'availableBranches' => $branches->accessibleBranches($request->user()),
            'selectedBranchId' => $branchId,
            'canChooseBranch' => $request->user()->isControllerAdministrator(),
        ]);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $branchId = $this->selectedFamilyBranchId($request);

        $terms = preg_split('/\s+/', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY);
        $students = Student::query()
            ->withoutGlobalScopes()
            ->select(['id', 'student_number', 'first_name', 'middle_name', 'last_name'])
            ->where('branch_id', $branchId)
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->where(function ($studentQuery) use ($term): void {
                        $studentQuery
                            ->whereInsensitiveLike('student_number', $term)
                            ->orWhereInsensitiveLike('first_name', $term)
                            ->orWhereInsensitiveLike('middle_name', $term)
                            ->orWhereInsensitiveLike('last_name', $term);
                    });
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(15)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
            ]);

        return response()->json(['data' => $students]);
    }

    public function storeResponsible(Request $request): RedirectResponse
    {
        $data = $this->validatedResponsible($request);
        $studentIds = $data['student_ids'] ?? [];
        $children = $data['children'] ?? [];
        unset($data['student_ids']);
        unset($data['children']);

        $person = DB::transaction(function () use ($data, $studentIds, $children): ResponsiblePerson {
            $person = ResponsiblePerson::create($data);
            $this->syncStudents($person, $studentIds);
            $this->createLinkedStudents($person, $children);

            return $person;
        });

        return redirect()->route('responsible.index', ['branch_id' => $person->branch_id])
            ->with('success', 'Parent and linked student profiles saved.');
    }

    public function editResponsible(ResponsiblePerson $responsiblePerson): View
    {
        $this->assertCanManageFamily($responsiblePerson);
        $responsiblePerson->load(['students' => fn ($students) => $students->withoutGlobalScopes()->where('students.branch_id', $responsiblePerson->branch_id), 'branch']);
        $selectedStudentIds = old('student_ids');
        $selectedStudents = is_array($selectedStudentIds)
            ? $this->studentsForPicker($selectedStudentIds, (int) $responsiblePerson->branch_id)
            : $responsiblePerson->students;

        return view('people.responsible-form', [
            'person' => $responsiblePerson,
            'teachers' => Teacher::query()
                ->orderBy('first_name')
                ->get(),
            'selectedStudents' => $selectedStudents,
            'referenceOptions' => $this->referenceOptions(),
        ]);
    }

    public function updateResponsible(Request $request, ResponsiblePerson $responsiblePerson): RedirectResponse
    {
        $this->assertCanManageFamily($responsiblePerson);
        $data = $this->validatedResponsible($request);
        $studentIds = $data['student_ids'] ?? [];
        $children = $data['children'] ?? [];
        unset($data['student_ids']);
        unset($data['children']);

        DB::transaction(function () use ($responsiblePerson, $data, $studentIds, $children): void {
            $responsiblePerson->update($data);
            $this->syncStudents($responsiblePerson, $studentIds);
            $this->createLinkedStudents($responsiblePerson, $children);
        });

        return redirect()->route('responsible.index', ['branch_id' => $responsiblePerson->branch_id])
            ->with('success', 'Responsible person updated.');
    }

    public function destroyResponsible(ResponsiblePerson $responsiblePerson, AuditService $audit): RedirectResponse
    {
        $this->assertCanManageFamily($responsiblePerson);
        if ($responsiblePerson->students()->withoutGlobalScopes()->exists()) {
            return back()->with('error', 'Remove or reassign the linked students before deleting this family contact.');
        }

        $responsiblePerson->delete();
        $audit->log('responsible_person.deleted', $responsiblePerson);

        return redirect()->route('students.families')->with('success', 'Unlinked family contact moved to the recycle bin.');
    }

    private function validatedResponsible(Request $request): array
    {
        $data = $request->validate([
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'parent_code' => ['nullable', 'string', 'max:50', Rule::unique('responsible_persons', 'parent_code')->ignore($request->route('responsiblePerson'))],
            'title' => ['nullable', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:teacher,father,mother,guardian,administrator,other'],
            'blood_type' => ['nullable', Rule::in($this->referenceOptions()['bloodTypes']->all())],
            'religion' => ['nullable', 'string', 'max:100'],
            'family_type' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'occupation_details' => ['nullable', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'register_number' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'town' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'secondary_nationality' => ['nullable', 'string', 'max:100'],
            'is_alumni' => ['nullable', 'boolean'],
            'is_deceased' => ['nullable', 'boolean'],
            'employment_status' => ['nullable', 'string', 'max:100'],
            'employer' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'work_address' => ['nullable', 'string', 'max:2000'],
            'family_status' => ['nullable', 'string', 'max:100'],
            'is_second_marriage' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
            'children' => ['nullable', 'array'],
            'children.*.student_number' => ['nullable', 'string', 'max:50', 'distinct', 'unique:students,student_number'],
            'children.*.first_name' => ['required', 'string', 'max:100'],
            'children.*.middle_name' => ['nullable', 'string', 'max:100'],
            'children.*.last_name' => ['required', 'string', 'max:100'],
            'children.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'children.*.date_of_birth' => ['nullable', 'date', 'before:today'],
            'children.*.phone' => ['nullable', 'string', 'max:30'],
            'children.*.email' => ['required', 'email', 'max:255', 'distinct', 'unique:students,email', 'unique:users,email'],
            'children.*.password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        foreach (['is_alumni', 'is_deceased', 'is_second_marriage'] as $field) {
            $data[$field] = $request->boolean($field);
        }

        $studentIds = collect($data['student_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $person = $request->route('responsiblePerson');
        $branchId = $person instanceof ResponsiblePerson
            ? (int) ($person->branch_id ?? app(BranchContext::class)->id())
            : $this->selectedFamilyBranchId($request);
        $data['branch_id'] = $branchId;
        if ($studentIds !== [] && Student::withoutGlobalScopes()->where('branch_id', $branchId)->whereKey($studentIds)->count() !== count($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => 'Every linked student must belong to the selected family branch.',
            ]);
        }

        return $data;
    }

    /** @return array{familyTypes: \Illuminate\Support\Collection, bloodTypes: \Illuminate\Support\Collection, districts: \Illuminate\Support\Collection, towns: \Illuminate\Support\Collection} */
    private function referenceOptions(): array
    {
        $entries = StudentReferenceEntry::query()->get();
        $codeEntries = $entries->where('reference', 'codes');
        $familyTypes = $codeEntries->filter(fn ($entry): bool => in_array(strtolower((string) $entry->category), ['family-type', 'family_type', 'family type'], true))->pluck('value');
        $bloodTypeEntries = $codeEntries->where('category', 'blood-type');
        $suppressedBloodTypes = $bloodTypeEntries->where('value', '__deleted__')->pluck('code');
        $bloodTypes = collect(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])
            ->reject(fn (string $value): bool => $suppressedBloodTypes->contains($value))
            ->merge($bloodTypeEntries->reject(fn ($entry): bool => $entry->value === '__deleted__')->pluck('value'))
            ->merge(ResponsiblePerson::query()->whereNotNull('blood_type')->pluck('blood_type'))
            ->filter(fn ($value): bool => filled($value) && $value !== '--')
            ->unique()
            ->sort()
            ->values();

        return [
            'familyTypes' => $familyTypes->merge(ResponsiblePerson::query()->whereNotNull('family_type')->pluck('family_type'))->filter()->unique()->sort()->values(),
            'bloodTypes' => $bloodTypes,
            'districts' => $entries->where('reference', 'district')->pluck('value')->merge(ResponsiblePerson::query()->whereNotNull('district')->pluck('district'))->filter()->unique()->sort()->values(),
            'towns' => $entries->where('reference', 'towns')->pluck('value')->merge(ResponsiblePerson::query()->whereNotNull('town')->pluck('town'))->filter()->unique()->sort()->values(),
        ];
    }

    private function syncStudents(ResponsiblePerson $person, array $studentIds): void
    {
        $relationship = str($person->type)->headline()->toString();
        $person->students()->sync(collect($studentIds)->mapWithKeys(fn ($studentId) => [
            $studentId => ['relationship' => $relationship, 'is_primary' => false],
        ])->all());
    }

    private function createLinkedStudents(ResponsiblePerson $person, array $children): void
    {
        $studentRole = Role::query()->where('name', 'student')->firstOrFail();
        $relationship = str($person->type)->headline()->toString();

        foreach ($children as $child) {
            $student = Student::create([
                'branch_id' => $person->branch_id,
                'student_number' => ($child['student_number'] ?? null) ?: 'STU-'.now()->format('Y').'-'.str_pad((string) ((Student::withoutGlobalScopes()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
                'first_name' => $child['first_name'],
                'middle_name' => $child['middle_name'] ?? null,
                'last_name' => $child['last_name'],
                'gender' => $child['gender'] ?? null,
                'date_of_birth' => $child['date_of_birth'] ?? null,
                'phone' => $child['phone'] ?? null,
                'email' => $child['email'],
            ]);
            $portalUser = User::create([
                'name' => $student->full_name,
                'email' => $student->email,
                'password' => $child['password'],
                'student_id' => $student->id,
                'is_active' => true,
            ]);
            $portalUser->roles()->sync([$studentRole->id]);
            if ($student->branch_id !== null) {
                $portalUser->branches()->sync([(int) $student->branch_id => ['is_default' => true, 'is_branch_administrator' => false]]);
            }
            $person->students()->syncWithoutDetaching([$student->id => ['relationship' => $relationship, 'is_primary' => true]]);
        }
    }

    private function studentsForPicker(array $studentIds, int $branchId)
    {
        return Student::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereKey(collect($studentIds)->filter()->map(fn ($id) => (int) $id)->unique()->all())
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    private function selectedFamilyBranchId(Request $request): int
    {
        $branches = app(BranchContext::class);
        $currentBranchId = $branches->id();
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : $currentBranchId;
        if (! $branchId || ! $branches->canUse($request->user(), $branchId)
            || (! $request->user()->isControllerAdministrator() && $branchId !== $currentBranchId)) {
            throw ValidationException::withMessages(['branch_id' => 'Choose an active branch you are allowed to manage.']);
        }

        return $branchId;
    }

    private function assertCanManageFamily(ResponsiblePerson $person): void
    {
        $user = auth()->user();
        $branchId = (int) ($person->branch_id ?? app(BranchContext::class)->id());
        abort_unless(app(BranchContext::class)->canUse($user, $branchId), 403);
        abort_if(! $user->isControllerAdministrator() && $branchId !== app(BranchContext::class)->id(), 403);
    }
}
