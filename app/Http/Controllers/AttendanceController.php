<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\StudentAttendance;
use App\Models\StudentRegistration;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TeacherAttendance;
use App\Services\AuditService;
use App\Services\SubstitutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function students(Request $request): View
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'attendance_date' => ['nullable', 'date'],
            'period_number' => ['nullable', 'integer', 'min:0', 'max:30'],
            'teacher_assignment_id' => ['nullable', 'integer', 'exists:teacher_assignments,id'],
            'status' => ['nullable', Rule::in(['present', 'absent', 'late', 'excused', 'left_early'])],
        ]);
        $selectedYearId = (int) ($filters['academic_year_id'] ?? AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->value('id'));
        $date = $filters['attendance_date'] ?? today()->toDateString();
        $period = (int) ($filters['period_number'] ?? 0);
        $assignment = ! empty($filters['teacher_assignment_id'])
            ? TeacherAssignment::query()->where('academic_year_id', $selectedYearId)->where('is_active', true)->findOrFail($filters['teacher_assignment_id'])
            : null;
        $registrations = StudentRegistration::query()
            ->with('student', 'grade', 'section')
            ->whereHas('student')
            ->where('status', 'active')
            ->where('academic_year_id', $selectedYearId)
            ->when($filters['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($assignment, fn ($query) => $query->where('grade_id', $assignment->grade_id)
                ->when($assignment->section_id, fn ($q, $id) => $q->where('section_id', $id)))
            ->orderBy('grade_id')->orderBy('section_id')
            ->get();
        $existing = StudentAttendance::query()
            ->whereDate('attendance_date', $date)
            ->where('period_number', $period)
            ->whereIn('student_registration_id', $registrations->modelKeys())
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->get()
            ->keyBy('student_registration_id');
        if (! empty($filters['status'])) {
            $registrations = $registrations->filter(fn ($registration) => $existing->has($registration->id))->values();
        }

        return view('attendance.students', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
            'assignments' => TeacherAssignment::query()->with('teacher', 'subject', 'grade', 'section')->where('is_active', true)->when($selectedYearId, fn ($query) => $query->where('academic_year_id', $selectedYearId))->get(),
            'registrations' => $registrations,
            'existing' => $existing,
            'filters' => array_replace($filters, ['academic_year_id' => $selectedYearId, 'attendance_date' => $date, 'period_number' => $period]),
        ]);
    }

    public function saveStudents(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'attendance_date' => ['required', 'date'],
            'period_number' => ['nullable', 'integer', 'min:0', 'max:30'],
            'teacher_assignment_id' => ['nullable', 'integer', 'exists:teacher_assignments,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.registration_id' => ['required', 'integer', 'distinct', 'exists:student_registrations,id'],
            'entries.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused', 'left_early'])],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $registrationIds = collect($data['entries'])->pluck('registration_id')->map(fn ($id) => (int) $id)->all();
        $registrations = StudentRegistration::query()
            ->whereIn('id', $registrationIds)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('status', 'active')
            ->whereHas('student')
            ->get()
            ->keyBy('id');
        if ($registrations->count() !== count($registrationIds)) {
            throw ValidationException::withMessages(['entries' => 'Every attendance entry must belong to an active registration in the selected academic year.']);
        }
        if (! empty($data['teacher_assignment_id'])) {
            $assignment = TeacherAssignment::query()->findOrFail($data['teacher_assignment_id']);
            if (! $assignment->is_active || (int) $assignment->academic_year_id !== (int) $data['academic_year_id']
                || $registrations->contains(fn ($registration) => (int) $registration->grade_id !== (int) $assignment->grade_id
                    || ($assignment->section_id && (int) $registration->section_id !== (int) $assignment->section_id))) {
                throw ValidationException::withMessages(['teacher_assignment_id' => 'Choose an active course assignment for the same academic year, grade, and section as every selected student.']);
            }
        }
        $period = (int) ($data['period_number'] ?? 0);

        DB::transaction(function () use ($data, $registrations, $period): void {
            foreach ($data['entries'] as $entry) {
                $registration = $registrations[(int) $entry['registration_id']];
                StudentAttendance::updateOrCreate(
                    [
                        'student_id' => $registration->student_id,
                        'attendance_date' => $data['attendance_date'],
                        'period_number' => $period,
                    ],
                    [
                        'student_registration_id' => $registration->id,
                        'academic_year_id' => $registration->academic_year_id,
                        'grade_id' => $registration->grade_id,
                        'section_id' => $registration->section_id,
                        'teacher_assignment_id' => $data['teacher_assignment_id'] ?? null,
                        'status' => $entry['status'],
                        'notes' => $entry['notes'] ?? null,
                        'recorded_by' => auth()->id(),
                    ],
                );
            }
        });
        $audit->log('student_attendance.bulk_saved', null, [], ['attendance_date' => $data['attendance_date'], 'period_number' => $period, 'entries' => count($data['entries'])]);

        return redirect()->route('attendance.students', array_filter([
            'academic_year_id' => $data['academic_year_id'],
            'attendance_date' => $data['attendance_date'],
            'period_number' => $period ?: null,
            'teacher_assignment_id' => $data['teacher_assignment_id'] ?? null,
            'grade_id' => $data['grade_id'] ?? null,
            'section_id' => $data['section_id'] ?? null,
            'student_id' => $data['student_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''))->with('success', 'Student attendance saved.');
    }

    public function teachers(Request $request): View
    {
        $filters = $request->validate([
            'attendance_date' => ['nullable', 'date'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'status' => ['nullable', Rule::in(['present', 'late', 'early_leave', 'excused_absence', 'unexcused_absence'])],
        ]);
        $date = $filters['attendance_date'] ?? today()->toDateString();
        $teachers = Teacher::query()->where('is_active', true)->when($filters['teacher_id'] ?? null, fn ($query, $id) => $query->whereKey($id))->orderBy('first_name')->get();
        $records = TeacherAttendance::query()->whereDate('attendance_date', $date)->whereIn('teacher_id', $teachers->modelKeys())->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->get()->keyBy('teacher_id');

        return view('attendance.teachers', compact('teachers', 'records', 'filters', 'date'));
    }

    public function saveTeacher(Request $request, SubstitutionService $substitutions, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'attendance_date' => ['required', 'date'],
            'checked_in_at' => ['nullable', 'date_format:H:i'],
            'checked_out_at' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['present', 'late', 'early_leave', 'excused_absence', 'unexcused_absence'])],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'early_leave_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $record = DB::transaction(function () use ($data): TeacherAttendance {
            return TeacherAttendance::updateOrCreate(
                ['teacher_id' => $data['teacher_id'], 'attendance_date' => $data['attendance_date']],
                $data + ['recorded_by' => auth()->id()],
            );
        });
        $substitutions->createForAbsence($record);
        $audit->log('teacher_attendance.saved', $record, [], $record->only(['teacher_id', 'attendance_date', 'status', 'checked_in_at', 'checked_out_at']));

        return redirect()->route('attendance.teachers', ['attendance_date' => $data['attendance_date']])->with('success', 'Teacher attendance saved. Substitute recommendations were generated when needed.');
    }
}
