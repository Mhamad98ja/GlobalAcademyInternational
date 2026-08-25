<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ResponsiblePerson;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($data, $studentIds): void {
            $person = ResponsiblePerson::create($data);
            $this->syncStudents($person, $studentIds);
        });

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

    private function validatedResponsible(Request $request): array
    {
        $data = $request->validate([
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:teacher,father,mother,guardian,administrator,other'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ]);

        $studentIds = collect($data['student_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        if ($studentIds !== [] && Student::query()->whereKey($studentIds)->count() !== count($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => 'One or more selected students are not available in this branch.',
            ]);
        }

        return $data;
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
