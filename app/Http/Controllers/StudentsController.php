<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Student;
use App\Models\ResponsiblePerson;
use App\Models\StudentSubjectExemption;
use App\Models\StudentSupportAssignment;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\StudentRegistration;
use App\Models\Teacher;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BranchContext;
use App\Services\StudentRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentsController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'mode' => ['nullable', 'in:student,registration'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);
        $terms = preg_split('/\s+/', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY);
        $matches = fn ($query) => $query->where(function ($students) use ($terms): void {
            foreach ($terms as $term) {
                $students->where(function ($studentQuery) use ($term): void {
                    $studentQuery->whereInsensitiveLike('student_number', $term)
                        ->orWhereInsensitiveLike('registration_number', $term)
                        ->orWhereInsensitiveLike('first_name', $term)
                        ->orWhereInsensitiveLike('middle_name', $term)
                        ->orWhereInsensitiveLike('last_name', $term);
                });
            }
        });

        if (($data['mode'] ?? 'student') === 'registration') {
            $registrations = StudentRegistration::query()
                ->with('student', 'grade', 'section', 'academicYear')
                ->where('status', 'active')
                ->when($data['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
                ->when($data['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
                ->when($data['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
                ->when($data['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
                ->whereHas('student', $matches)
                ->latest('registered_on')
                ->limit(25)
                ->get();

            return response()->json(['data' => $registrations->map(fn (StudentRegistration $registration): array => [
                'id' => $registration->id,
                'student_id' => $registration->student_id,
                'registration_id' => $registration->id,
                'student_number' => $registration->student?->student_number,
                'full_name' => $registration->student?->full_name,
                'academic_year_id' => $registration->academic_year_id,
                'grade_id' => $registration->grade_id,
                'section_id' => $registration->section_id,
                'section_label' => $registration->section?->name ?? 'All sections',
                'context' => trim(($registration->academicYear?->name ?? '').' · '.($registration->grade?->name ?? '').($registration->section ? ' '.$registration->section->name : '')),
                'label' => trim(($registration->student?->full_name ?? '').' · '.($registration->grade?->name ?? '').($registration->section ? ' '.$registration->section->name : '')),
            ])]);
        }

        $students = Student::query()
            ->when(($data['academic_year_id'] ?? null) || ($data['grade_id'] ?? null) || ($data['section_id'] ?? null), fn ($query) => $query->whereHas('registrations', fn ($registrations) => $registrations
                ->where('status', 'active')
                ->when($data['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
                ->when($data['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
                ->when($data['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))))
            ->where($matches)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(25)
            ->get();

        return response()->json(['data' => $students->map(fn (Student $student): array => [
            'id' => $student->id,
            'student_id' => $student->id,
            'student_number' => $student->student_number,
            'full_name' => $student->full_name,
            'label' => trim(($student->student_number ? $student->student_number.' · ' : '').$student->full_name),
        ])]);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'search_in' => ['nullable', 'in:student,parent'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'registration_status' => ['nullable', 'in:active,inactive,withdrawn,completed'],
            'gender' => ['nullable', 'in:male,female'],
            'sort' => ['nullable', 'in:asc,desc'],
        ]);
        $academicYearId = $filters['academic_year_id'] ?? null;
        $gradeId = $filters['grade_id'] ?? null;
        $sectionId = $filters['section_id'] ?? null;
        $registrationStatus = $filters['registration_status'] ?? null;
        $sort = ($filters['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $matchingRegistration = fn ($query) => $query
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($gradeId, fn ($q) => $q->where('grade_id', $gradeId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($registrationStatus, fn ($q) => $q->where('status', $registrationStatus));

        $students = Student::query()
            ->with(['registrations' => fn ($registrations) => $matchingRegistration($registrations)->with('academicYear', 'grade', 'section')->orderByDesc('registered_on')->orderByDesc('id')])
            ->when($filters['search'] ?? null, function ($query, $search) use ($filters): void {
                if (($filters['search_in'] ?? 'student') === 'parent') {
                    $query->whereHas('responsiblePersons', fn ($people) => $people
                        ->whereInsensitiveLike('full_name', $search)
                        ->orWhereInsensitiveLike('parent_code', $search)
                        ->orWhereInsensitiveLike('phone', $search));

                    return;
                }

                $query->where(function ($students) use ($search): void {
                    $students->whereInsensitiveLike('student_number', $search)
                        ->orWhereInsensitiveLike('registration_number', $search)
                        ->orWhereInsensitiveLike('phone', $search)
                        ->orWhere(function ($names) use ($search): void {
                            foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                                $names->where(fn ($name) => $name->whereInsensitiveLike('first_name', $part)
                                    ->orWhereInsensitiveLike('middle_name', $part)
                                    ->orWhereInsensitiveLike('last_name', $part));
                            }
                        });
                });
            })
            ->when($filters['code'] ?? null, fn ($query, string $code) => $query->where(function ($students) use ($code): void {
                $students->whereInsensitiveLike('student_number', $code)
                    ->orWhereInsensitiveLike('registration_number', $code);
            }))
            ->when($filters['last_name'] ?? null, fn ($query, string $lastName) => $query->whereInsensitiveLike('last_name', $lastName))
            ->when($filters['first_name'] ?? null, fn ($query, string $firstName) => $query->whereInsensitiveLike('first_name', $firstName))
            ->when($academicYearId || $gradeId || $sectionId || $registrationStatus, fn ($query) => $query->whereHas('registrations', $matchingRegistration))
            ->when($filters['gender'] ?? null, fn ($query, $gender) => $query->where('gender', $gender))
            ->orderBy('first_name', $sort)
            ->orderBy('last_name', $sort);

        return view('students.index', [
            'students' => $students->paginate(15)->withQueryString(),
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'grades' => Grade::with('sections')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $responsiblePerson = $request->integer('responsible_person_id')
            ? ResponsiblePerson::query()->where('branch_id', app(BranchContext::class)->id())->find($request->integer('responsible_person_id'))
            : null;

        return view('students.form', ['student' => new Student, 'registrations' => false, 'responsiblePerson' => $responsiblePerson]);
    }

    public function store(StoreStudentRequest $request, StudentRegistrationService $registrations): RedirectResponse
    {
        return DB::transaction(function () use ($request, $registrations): RedirectResponse {
            $data = $request->validated();
            $portalPassword = $data['password'];
            $responsiblePersonId = $data['responsible_person_id'] ?? null;
            $responsiblePerson = $responsiblePersonId ? ResponsiblePerson::query()->findOrFail($responsiblePersonId) : null;
            if ($responsiblePerson && (int) $responsiblePerson->branch_id !== (int) app(BranchContext::class)->id()) {
                throw ValidationException::withMessages(['responsible_person_id' => 'The parent and student must belong to the same branch.']);
            }
            unset($data['password']);
            unset($data['responsible_person_id']);
            $data['student_number'] = $data['student_number'] ?? 'STU-'.now()->format('Y').'-'.str_pad((string) ((Student::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
            $student = Student::create($data);
            if ($responsiblePerson) {
                $responsiblePerson->students()->syncWithoutDetaching([$student->id => ['relationship' => 'Parent / guardian', 'is_primary' => true]]);
            }
            $portalUser = User::create([
                'name' => $student->full_name,
                'email' => $student->email,
                'password' => $portalPassword,
                'student_id' => $student->id,
                'is_active' => true,
            ]);
            $studentRole = Role::query()->where('name', 'student')->firstOrFail();
            $portalUser->roles()->sync([$studentRole->id]);
            $this->syncStudentPortalBranch($portalUser, $student);

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

            return redirect()->route('students.show', $student)->with('success', 'Student profile, portal account, and registration were saved. The student can now sign in with their email and password.');
        });
    }

    public function show(Student $student, Request $request): View
    {
        $relations = [
            'registrations' => fn ($query) => $query->with('academicYear', 'grade', 'section', 'teacher')->orderByDesc('registered_on')->orderByDesc('id'),
            'responsiblePersons.students',
        ];
        $canViewFinancials = $request->user()->canViewFinancials();
        $canViewAttendance = $request->user()->hasPermission('attendance.student.manage');
        $canViewDiscipline = $request->user()->hasPermission('discipline.view');
        $canViewMedical = $request->user()->hasPermission('medical.view');
        $canViewGrades = $request->user()->hasPermission('grades.enter');
        $canViewExamAbsences = $request->user()->hasPermission('academic_year.manage');
        $canViewPerformance = $request->user()->hasPermission('performance.view') || $request->user()->hasPermission('performance.manage');
        if ($canViewFinancials) {
            $relations[] = 'invoices';
        }
        if ($canViewAttendance) {
            $relations[] = 'attendances';
        }
        if ($canViewDiscipline) {
            $relations[] = 'incidents.category';
            $relations[] = 'incidents.type';
        }
        if ($canViewMedical) {
            $relations[] = 'medicalRecords';
        }
        if ($canViewGrades) {
            $relations[] = 'gradeEntries.assessment.subject';
        }
        if ($canViewExamAbsences) {
            $relations[] = 'examAbsences.assessment.subject';
        }
        if ($canViewPerformance) {
            $relations[] = 'performanceEvaluations.assignment.subject';
            $relations[] = 'performanceEvaluations.teacher';
        }
        $student->load($relations);

        $activeRegistration = $student->registrations->firstWhere('status', 'active') ?? $student->registrations->first();
        $subjects = $activeRegistration
            ? Subject::query()->where('is_active', true)->whereHas('assignments', fn ($assignments) => $assignments
                ->where('academic_year_id', $activeRegistration->academic_year_id)
                ->where('grade_id', $activeRegistration->grade_id)
                ->where(fn ($sections) => $sections->whereNull('section_id')->orWhere('section_id', $activeRegistration->section_id)))->orderBy('name')->get()
            : collect();
        $student->load(['supportAssignments.user', 'subjectExemptions.subject']);
        $isExempt = fn (int $registrationId, ?int $subjectId): bool => $subjectId !== null && $student->subjectExemptions->contains(fn ($exemption) => $exemption->student_registration_id === $registrationId && $exemption->subject_id === $subjectId);
        if ($canViewGrades) {
            $student->setRelation('gradeEntries', $student->gradeEntries->reject(fn ($entry) => $isExempt($entry->student_registration_id, $entry->assessment?->subject_id ?? $entry->assessment?->assignment?->subject_id)));
        }
        if ($canViewExamAbsences) {
            $student->setRelation('examAbsences', $student->examAbsences->reject(fn ($entry) => $isExempt($entry->student_registration_id, $entry->assessment?->subject_id ?? $entry->assessment?->assignment?->subject_id)));
        }
        if ($canViewPerformance) {
            $student->setRelation('performanceEvaluations', $student->performanceEvaluations->reject(fn ($entry) => $entry->assignment && $isExempt($entry->student_registration_id, $entry->assignment->subject_id)));
        }
        $supportUsers = User::query()->where('is_active', true)->whereNull('student_id')->orderBy('name')->get();

        return view('students.show', compact('student', 'canViewFinancials', 'canViewAttendance', 'canViewDiscipline', 'canViewMedical', 'canViewGrades', 'canViewExamAbsences', 'canViewPerformance', 'subjects', 'supportUsers'));
    }

    public function storeSupportAssignment(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'assigned_on' => ['nullable', 'date'],
        ]);
        StudentSupportAssignment::create($data + ['student_id' => $student->id, 'status' => 'active']);

        return redirect()->route('students.show', [$student, 'tab' => 'general'])->with('success', 'Student support professional assigned.');
    }

    public function storeSubjectExemption(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'student_registration_id' => ['required', 'integer', 'exists:student_registrations,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $registration = StudentRegistration::query()->whereKey($data['student_registration_id'])->where('student_id', $student->id)->firstOrFail();
        abort_unless(TeacherAssignment::query()->where('academic_year_id', $registration->academic_year_id)->where('grade_id', $registration->grade_id)->where('subject_id', $data['subject_id'])->where(fn ($sections) => $sections->whereNull('section_id')->orWhere('section_id', $registration->section_id))->exists(), 422, 'The subject is not assigned to this student class.');
        StudentSubjectExemption::updateOrCreate(
            ['student_id' => $student->id, 'student_registration_id' => $registration->id, 'subject_id' => $data['subject_id']],
            ['reason' => $data['reason'] ?? null, 'approved_by' => $request->user()->id, 'created_by' => $request->user()->id],
        );

        return redirect()->route('students.show', [$student, 'tab' => 'subjects'])->with('success', 'Subject exemption saved.');
    }

    public function destroySubjectExemption(Student $student, StudentSubjectExemption $exemption): RedirectResponse
    {
        abort_unless($exemption->student_id === $student->id, 404);
        $exemption->delete();

        return redirect()->route('students.show', [$student, 'tab' => 'subjects'])->with('success', 'Subject exemption removed.');
    }

    public function edit(Student $student): View
    {
        return view('students.form', ['student' => $student, 'registrations' => false]);
    }

    public function update(StoreStudentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();
        $password = $data['password'] ?? null;
        unset($data['password']);
        unset($data['responsible_person_id']);
        $student->update($data);
        if ($student->portalUser) {
            $attributes = ['name' => $student->full_name, 'email' => $student->email];
            if ($password) {
                $attributes['password'] = $password;
            }
            $student->portalUser->update($attributes);
        } elseif ($password) {
            $portalUser = User::create(['name' => $student->full_name, 'email' => $student->email, 'password' => $password, 'student_id' => $student->id, 'is_active' => true]);
            $studentRole = Role::query()->where('name', 'student')->firstOrFail();
            $portalUser->roles()->sync([$studentRole->id]);
            $this->syncStudentPortalBranch($portalUser, $student);
        }
        if ($student->portalUser && $student->branch_id !== null) {
            $this->syncStudentPortalBranch($student->portalUser, $student);
        }

        return redirect()->route('students.show', $student)->with('success', 'Student profile updated.');
    }

    private function syncStudentPortalBranch(User $portalUser, Student $student): void
    {
        if ($student->branch_id !== null) {
            $portalUser->branches()->sync([(int) $student->branch_id => ['is_default' => true, 'is_branch_administrator' => false]]);
        }
    }

    public function destroy(Student $student, AuditService $audit): RedirectResponse
    {
        $student->delete();
        $audit->log('student.deleted', $student);

        return redirect()->route('students.index')->with('success', 'Student moved to recycle bin.');
    }

    public function updatePhoto(Request $request, Student $student, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=3000,max_height=3000'],
        ]);
        $old = $student->photo_path;
        $path = $data['photo']->store('student-photos/'.$student->public_id, 'public');
        $student->update(['photo_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }
        $audit->log('student.photo_updated', $student, ['photo_path' => $old], ['photo_path' => $path]);

        return back()->with('success', 'Student photo saved.');
    }

    public function photo(Student $student)
    {
        abort_unless($student->photo_path && Storage::disk('public')->exists($student->photo_path), 404);

        return Storage::disk('public')->response($student->photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
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
            'registration' => new StudentRegistration,
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
