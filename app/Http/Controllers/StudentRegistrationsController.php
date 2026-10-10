<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\Teacher;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentRegistrationsController extends Controller
{
    public function edit(Student $student, StudentRegistration $registration): View
    {
        $this->ensureBelongsToStudent($student, $registration);

        return $this->form($student, $registration);
    }

    public function update(Request $request, Student $student, StudentRegistration $registration, AuditService $audit): RedirectResponse
    {
        $this->ensureBelongsToStudent($student, $registration);
        $data = $request->validate($this->rules($request, $student, $registration));

        DB::transaction(function () use ($registration, $data, $audit): void {
            $old = $registration->only(['academic_year_id', 'grade_id', 'section_id', 'teacher_id', 'registered_on', 'notes']);
            $registration->update($data);
            $audit->log('student.registration_updated', $registration, $old, $registration->only(['academic_year_id', 'grade_id', 'section_id', 'teacher_id', 'registered_on', 'notes']));
        });

        return redirect()->route('students.show', $student)->with('success', 'Academic registration updated.');
    }

    private function form(Student $student, StudentRegistration $registration): View
    {
        return view('students.registration', [
            'student' => $student,
            'registration' => $registration,
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'grades' => Grade::with('sections')->orderBy('sort_order')->get(),
            'teachers' => Teacher::query()
                ->where(function ($query) use ($registration): void {
                    $query->where('is_active', true);
                    if ($registration->teacher_id) {
                        $query->orWhere('id', $registration->teacher_id);
                    }
                })
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    private function rules(Request $request, Student $student, StudentRegistration $registration): array
    {
        $academicYear = Rule::unique('student_registrations', 'academic_year_id')
            ->where(fn ($query) => $query->where('student_id', $student->id))
            ->ignore($registration->id);

        return [
            'academic_year_id' => ['required', 'exists:academic_years,id', $academicYear],
            'grade_id' => ['required', 'exists:grades,id'],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->where('grade_id', $request->input('grade_id'))],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'registered_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    private function ensureBelongsToStudent(Student $student, StudentRegistration $registration): void
    {
        abort_unless($registration->student_id === $student->id, 404);
    }
}
