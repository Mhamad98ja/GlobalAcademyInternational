<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditService;
use App\Services\StudentRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'registration_status' => ['nullable', 'in:active,inactive,withdrawn,completed'],
            'sort' => ['nullable', 'in:asc,desc'],
        ]);
        $gradeId = $filters['grade_id'] ?? null;
        $sectionId = $filters['section_id'] ?? null;
        $registrationStatus = $filters['registration_status'] ?? null;
        $sort = ($filters['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $students = Student::query()
            ->with(['registrations' => fn ($registrations) => $registrations->with('academicYear')->orderByDesc('registered_on')])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($students) => $students
                ->where('student_number', 'like', '%'.$search.'%')
                ->orWhere('registration_number', 'like', '%'.$search.'%')
                ->orWhere('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')))
            ->when($gradeId || $sectionId, function ($query) use ($gradeId, $sectionId): void {
                $query->whereHas('registrations', function ($registrations) use ($gradeId, $sectionId): void {
                    $registrations
                        ->when($gradeId, fn ($registrationQuery) => $registrationQuery->where('grade_id', $gradeId))
                        ->when($sectionId, fn ($registrationQuery) => $registrationQuery->where('section_id', $sectionId));
                });
            })
            ->when($registrationStatus, fn ($query, $status) => $query->whereHas('registrations', fn ($registrations) => $registrations->where('status', $status)))
            ->orderBy('first_name', $sort)
            ->orderBy('last_name', $sort);

        return view('students.index', [
            'students' => $students->paginate(15)->withQueryString(),
            'grades' => Grade::with('sections')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('students.form', ['student' => new Student(), 'registrations' => false]);
    }

    public function store(StoreStudentRequest $request, StudentRegistrationService $registrations): RedirectResponse
    {
        return DB::transaction(function () use ($request, $registrations): RedirectResponse {
            $data = $request->validated();
            $data['student_number'] = $data['student_number'] ?: 'STU-'.now()->format('Y').'-'.str_pad((string) ((Student::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
            $student = Student::create($data);

            if ($request->filled('academic_year_id')) {
                $registrations->register($student, $request->validate([
                    'academic_year_id' => ['required', 'exists:academic_years,id'],
                    'grade_id' => ['required', 'exists:grades,id'],
                    'section_id' => ['nullable', 'exists:sections,id'],
                    'teacher_id' => ['nullable', 'exists:teachers,id'],
                    'registered_on' => ['required', 'date'],
                    'registration_notes' => ['nullable', 'string', 'max:3000'],
                ]) + ['notes' => $request->input('registration_notes')], $request->user());
            }

            return redirect()->route('students.show', $student)->with('success', 'Student profile and registration were saved.');
        });
    }

    public function show(Student $student): View
    {
        $student->load(['registrations.academicYear', 'registrations.grade', 'registrations.section', 'responsiblePersons', 'invoices']);

        return view('students.show', compact('student'));
    }

    public function edit(Student $student): View
    {
        return view('students.form', ['student' => $student, 'registrations' => false]);
    }

    public function update(StoreStudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->validated());

        return redirect()->route('students.show', $student)->with('success', 'Student profile updated.');
    }

    public function destroy(Student $student, AuditService $audit): RedirectResponse
    {
        $student->delete();
        $audit->log('student.deleted', $student);

        return redirect()->route('students.index')->with('success', 'Student moved to recycle bin.');
    }

    public function leaveForm(Student $student): View
    {
        $student->load(['registrations' => fn ($registrations) => $registrations
            ->where('status', 'active')
            ->with('academicYear', 'grade', 'section')
            ->latest('registered_on')]);

        return view('students.leave', compact('student'));
    }

    public function leave(Request $request, Student $student, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'withdrawn_on' => ['required', 'date', 'before_or_equal:today'],
            'withdrawal_reason' => ['nullable', 'string', 'max:3000'],
        ]);
        $registrations = $student->registrations()->where('status', 'active')->get();

        if ($registrations->isEmpty()) {
            return redirect()->route('students.show', $student)->with('error', 'This student has no active academic registration to mark as withdrawn.');
        }

        DB::transaction(function () use ($student, $registrations, $data, $audit): void {
            $registrationIds = $registrations->modelKeys();
            $student->registrations()->whereIn('id', $registrationIds)->update([
                'status' => 'withdrawn',
                'withdrawn_on' => $data['withdrawn_on'],
                'withdrawal_reason' => $data['withdrawal_reason'] ?? null,
                'updated_at' => now(),
            ]);
            $audit->log('student.left_school', $student, ['active_registration_ids' => $registrationIds], [
                'withdrawn_on' => $data['withdrawn_on'],
                'withdrawal_reason' => $data['withdrawal_reason'] ?? null,
            ]);
        });

        return redirect()->route('students.show', $student)->with('success', 'Student profile was kept and the active academic registration was marked as withdrawn.');
    }

    public function registerForm(Student $student): View
    {
        return view('students.registration', [
            'student' => $student,
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'grades' => Grade::with('sections')->orderBy('sort_order')->get(),
            'teachers' => Teacher::where('is_active', true)->orderBy('first_name')->get(),
            'registration' => new \App\Models\StudentRegistration(),
        ]);
    }

    public function register(Request $request, Student $student, StudentRegistrationService $service): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'grade_id' => ['required', 'exists:grades,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'registered_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $service->register($student, $data, $request->user());

        return redirect()->route('students.show', $student)->with('success', 'Academic-year registration created.');
    }
}
