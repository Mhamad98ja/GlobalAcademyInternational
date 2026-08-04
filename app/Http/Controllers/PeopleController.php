<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ResponsiblePerson;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PeopleController extends Controller
{
    public function teachers(Request $request): View
    {
        return view('people.teachers', [
            'teachers' => Teacher::query()
                ->when($request->filled('search'), fn ($query) => $query
                    ->where('first_name', 'like', '%'.$request->search.'%')
                    ->orWhere('last_name', 'like', '%'.$request->search.'%')
                    ->orWhere('employee_number', 'like', '%'.$request->search.'%'))
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
                    ->where('full_name', 'like', '%'.$request->search.'%')
                    ->orWhere('phone', 'like', '%'.$request->search.'%'))
                ->orderBy('full_name')
                ->paginate(15)
                ->withQueryString(),
            'teachers' => Teacher::where('is_active', true)->orderBy('first_name')->get(),
            'students' => Student::orderBy('first_name')->orderBy('last_name')->get(),
        ]);
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

        return view('people.responsible-form', [
            'person' => $responsiblePerson,
            'teachers' => Teacher::where('is_active', true)
                ->when($responsiblePerson->teacher_id, fn ($query) => $query->orWhereKey($responsiblePerson->teacher_id))
                ->orderBy('first_name')
                ->get(),
            'students' => Student::orderBy('first_name')->orderBy('last_name')->get(),
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
        return $request->validate([
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
    }

    private function syncStudents(ResponsiblePerson $person, array $studentIds): void
    {
        $relationship = str($person->type)->headline()->toString();
        $person->students()->sync(collect($studentIds)->mapWithKeys(fn ($studentId) => [
            $studentId => ['relationship' => $relationship, 'is_primary' => false],
        ])->all());
    }
}
