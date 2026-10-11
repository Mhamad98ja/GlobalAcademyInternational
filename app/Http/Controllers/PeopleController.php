<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ResponsiblePerson;
use App\Models\StudentReferenceEntry;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        return view('people.responsible', [
            'people' => ResponsiblePerson::query()
                ->with(['teacher', 'students'])
                ->when($request->filled('search'), fn ($query) => $query
                    ->whereInsensitiveLike('full_name', $request->search)
                    ->orWhereInsensitiveLike('phone', $request->search))
                ->orderBy('full_name')
                ->paginate(15)
                ->withQueryString(),
            'teachers' => Teacher::where('is_active', true)->orderBy('first_name')->get(),
            'selectedStudents' => $this->studentsForPicker((array) old('student_ids', [])),
            'referenceOptions' => $this->referenceOptions(),
        ]);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $terms = preg_split('/\s+/', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY);
        $students = Student::query()
            ->select(['id', 'student_number', 'first_name', 'middle_name', 'last_name'])
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
        unset($data['student_ids']);

        $person = DB::transaction(function () use ($data, $studentIds): ResponsiblePerson {
            $person = ResponsiblePerson::create($data);
            $this->syncStudents($person, $studentIds);

            return $person;
        });

        if ($request->boolean('add_student')) {
            return redirect()->route('students.create', ['responsible_person_id' => $person->id])->with('success', 'Parent saved. Add the student profile below; you can return and add more students later.');
        }

        return back()->with('success', 'Responsible person and student links saved.');
    }

    public function editResponsible(ResponsiblePerson $responsiblePerson): View
    {
        $responsiblePerson->load('students');
        $selectedStudentIds = old('student_ids');
        $selectedStudents = is_array($selectedStudentIds)
            ? $this->studentsForPicker($selectedStudentIds)
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
        $data = $this->validatedResponsible($request);
        $studentIds = $data['student_ids'] ?? [];
        unset($data['student_ids']);

        DB::transaction(function () use ($responsiblePerson, $data, $studentIds): void {
            $responsiblePerson->update($data);
            $this->syncStudents($responsiblePerson, $studentIds);
        });

        return redirect()->route('responsible.index')->with('success', 'Responsible person updated.');
    }

    public function destroyResponsible(ResponsiblePerson $responsiblePerson, AuditService $audit): RedirectResponse
    {
        if ($responsiblePerson->students()->exists()) {
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
        ]);

        foreach (['is_alumni', 'is_deceased', 'is_second_marriage'] as $field) {
            $data[$field] = $request->boolean($field);
        }

        $studentIds = collect($data['student_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        if ($studentIds !== [] && Student::query()->whereKey($studentIds)->count() !== count($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => 'One or more selected students are not available in this branch.',
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

    private function studentsForPicker(array $studentIds)
    {
        return Student::query()
            ->whereKey(collect($studentIds)->filter()->map(fn ($id) => (int) $id)->unique()->all())
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
