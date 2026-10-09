<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CourseContent;
use App\Models\Grade;
use App\Models\SchoolAnnouncement;
use App\Models\SchoolEvent;
use App\Models\SchoolMessage;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentPerformanceEvaluation;
use App\Models\StudentRegistration;
use App\Models\TeacherAssignment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AuditService;
use App\Notifications\StudentPortalNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CollaborationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $activeAcademicYear = AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->first();
        $section = (string) $request->route('section', $request->query('section', ''));
        $search = trim((string) $request->query('q', ''));
        $gradeId = $request->integer('grade_id') ?: null;
        $date = $request->date('date');
        $calendarFilters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $calendarDate = Carbon::create(
            (int) ($calendarFilters['year'] ?? today()->year),
            (int) ($calendarFilters['month'] ?? today()->month),
            1,
        );
        $assignments = $this->availableAssignments($user, $activeAcademicYear?->id);
        $canManage = $user->hasPermission('collaboration.manage');
        $canManagePerformance = $user->hasPermission('performance.manage');
        $canViewPerformance = $user->hasPermission('performance.view') || $canManagePerformance;

        $announcements = SchoolAnnouncement::query()
            ->with('grade', 'section', 'createdBy')
            ->when(! $canManage, fn (Builder $query) => $query->whereNotNull('published_at')->where('published_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
            ->latest('published_at')
            ->latest('id')
            ->take(12)
            ->get();

        $contents = CourseContent::query()
            ->with('assignment.teacher', 'assignment.subject', 'assignment.grade', 'assignment.section', 'createdBy')
            ->when(! $canManage, function (Builder $query) use ($user): void {
                $query->where(function (Builder $contents) use ($user): void {
                    $contents->whereNotNull('published_at')->where('published_at', '<=', now());
                    if ($user->teacher_id) {
                        $contents->orWhere('created_by', $user->id);
                    }
                });
            })
            ->when($activeAcademicYear, fn (Builder $query) => $query->whereHas('assignment', fn (Builder $assignments) => $assignments->where('academic_year_id', $activeAcademicYear->id)))
            ->when($gradeId, fn (Builder $query, int $id) => $query->whereHas('assignment', fn (Builder $assignment) => $assignment->where('grade_id', $id)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $contents) => $contents
                ->whereInsensitiveLike('title', $search)
                ->orWhereInsensitiveLike('content', $search)))
            ->when($date, fn (Builder $query) => $query->whereDate('due_on', $date))
            ->latest('published_at')
            ->latest('id')
            ->take(15)
            ->get();

        $events = SchoolEvent::query()
            ->with('grade', 'section', 'createdBy')
            ->where('starts_at', '>=', now()->startOfDay())
            ->when($activeAcademicYear, fn (Builder $query) => $query->where(fn (Builder $events) => $events->whereNull('academic_year_id')->orWhere('academic_year_id', $activeAcademicYear->id)))
            ->when($date, fn (Builder $query) => $query->whereDate('starts_at', $date))
            ->orderBy('starts_at')
            ->take(12)
            ->get();

        $evaluations = $canViewPerformance
            ? StudentPerformanceEvaluation::query()
                ->with('student', 'registration.grade', 'registration.section', 'assignment.subject', 'teacher')
                ->when($activeAcademicYear, fn (Builder $query) => $query->whereHas('registration', fn (Builder $registrations) => $registrations->where('academic_year_id', $activeAcademicYear->id)))
                ->when($gradeId, fn (Builder $query, int $id) => $query->whereHas('registration', fn (Builder $registrations) => $registrations->where('grade_id', $id)))
                ->when($user->teacher_id && ! $this->canManageAllAssignments($user), fn (Builder $query) => $query->where('teacher_id', $user->teacher_id))
                ->latest('observed_on')
                ->latest('id')
                ->take(15)
                ->get()
            : collect();

        $teachers = Teacher::query()
            ->where('is_active', true)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $teachers) => $teachers
                ->whereInsensitiveLike('first_name', $search)
                ->orWhereInsensitiveLike('last_name', $search)
                ->orWhereInsensitiveLike('employee_number', $search)))
            ->orderBy('first_name')->orderBy('last_name')->take(30)->get();
        $messages = SchoolMessage::query()->with('sender')->latest('sent_at')->latest('id')->take(30)->get();

        return view('collaboration.index', [
            'activeAcademicYear' => $activeAcademicYear,
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
            'assignments' => $assignments,
            'registrations' => $canManagePerformance ? $this->availableRegistrations($assignments, $activeAcademicYear?->id) : collect(),
            'announcements' => $announcements,
            'contents' => $contents,
            'events' => $events,
            'evaluations' => $evaluations,
            'teachers' => $teachers,
            'messages' => $messages,
            'canManage' => $canManage,
            'canManagePerformance' => $canManagePerformance,
            'canViewPerformance' => $canViewPerformance,
            'section' => $section,
            'search' => $search,
            'selectedGradeId' => $gradeId,
            'selectedDate' => $date?->toDateString(),
            'calendarDate' => $calendarDate,
        ]);
    }

    public function storeAnnouncement(Request $request, AuditService $audit): RedirectResponse
    {
        $announcement = SchoolAnnouncement::create($this->audienceData($request) + $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]) + [
            'published_at' => $request->boolean('publish_now') ? now() : null,
            'created_by' => $request->user()->id,
        ]);
        $audit->log('school_announcement.created', $announcement, [], $announcement->only(['title', 'audience', 'published_at']));

        return back()->with('success', $announcement->published_at ? 'Announcement published.' : 'Announcement saved as a draft.');
    }

    public function storeCourseContent(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'teacher_assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['nullable', 'string', 'max:10000'],
            'content_type' => ['required', Rule::in(['lesson', 'homework', 'document', 'link', 'video', 'other'])],
            'resource_url' => ['nullable', 'url', 'max:2000'],
            'available_from' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
        ]);
        $assignment = $this->authorizedAssignment($request->user(), (int) $data['teacher_assignment_id']);
        if (! $assignment->is_active) {
            throw ValidationException::withMessages(['teacher_assignment_id' => 'Select an active subject assignment. Inactive assignments cannot receive new agenda items.']);
        }
        $content = CourseContent::create($data + [
            'teacher_assignment_id' => $assignment->id,
            'published_at' => $request->boolean('publish_now') ? now() : null,
            'created_by' => $request->user()->id,
        ]);
        if ($content->published_at) {
            $this->notifyCourseAudience($assignment, $content->title, 'A new '.$content->content_type.' was published for '.$assignment->subject?->name.'.');
        }
        $audit->log('course_content.created', $content, [], $content->only(['teacher_assignment_id', 'title', 'content_type', 'published_at']));

        return back()->with('success', $content->published_at ? 'Subject agenda item published.' : 'Subject agenda item saved as a draft.');
    }

    public function updateCourseContent(Request $request, CourseContent $courseContent, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'teacher_assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['nullable', 'string', 'max:10000'],
            'content_type' => ['required', Rule::in(['lesson', 'homework', 'document', 'link', 'video', 'other'])],
            'resource_url' => ['nullable', 'url', 'max:2000'],
            'available_from' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
        ]);
        $assignment = $this->authorizedAssignment($request->user(), (int) $data['teacher_assignment_id']);
        $wasPublished = $courseContent->published_at !== null;
        $courseContent->update($data + ['teacher_assignment_id' => $assignment->id, 'published_at' => $wasPublished || $request->boolean('publish_now') ? ($courseContent->published_at ?? now()) : null]);
        if (! $wasPublished && $courseContent->published_at) {
            $this->notifyCourseAudience($assignment, $courseContent->title, 'A new '.$courseContent->content_type.' was published for '.$assignment->subject?->name.'.');
        }
        $audit->log('course_content.updated', $courseContent, [], $courseContent->only(['teacher_assignment_id', 'title', 'content_type', 'published_at']));

        return back()->with('success', 'Agenda item updated.');
    }

    public function destroyCourseContent(Request $request, CourseContent $courseContent, AuditService $audit): RedirectResponse
    {
        $this->authorizedAssignment($request->user(), (int) $courseContent->teacher_assignment_id);
        $title = $courseContent->title;
        $courseContent->delete();
        $audit->log('course_content.deleted', $courseContent, ['title' => $title], []);

        return back()->with('success', 'Agenda item deleted.');
    }

    public function storeEvent(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $event = SchoolEvent::create($this->audienceData($request) + $data + ['created_by' => $request->user()->id]);
        $audit->log('school_event.created', $event, [], $event->only(['title', 'audience', 'starts_at', 'ends_at']));

        return back()->with('success', 'Calendar event created.');
    }

    public function storePerformanceEvaluation(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'student_registration_id' => ['required', 'integer', 'exists:student_registrations,id'],
            'teacher_assignment_id' => ['nullable', 'integer', 'exists:teacher_assignments,id'],
            'category' => ['required', Rule::in(['participation', 'behavior', 'effort', 'homework', 'engagement', 'other'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'observed_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        $registration = StudentRegistration::query()->findOrFail($data['student_registration_id']);
        $assignment = isset($data['teacher_assignment_id']) && $data['teacher_assignment_id']
            ? $this->authorizedAssignment($request->user(), (int) $data['teacher_assignment_id'])
            : null;
        if ($assignment && ($assignment->academic_year_id !== $registration->academic_year_id || $assignment->grade_id !== $registration->grade_id || ($assignment->section_id && $assignment->section_id !== $registration->section_id))) {
            throw ValidationException::withMessages(['teacher_assignment_id' => 'The selected teacher subject assignment does not apply to this student registration.']);
        }
        if (! $assignment && ! $this->canManageAllAssignments($request->user())) {
            throw ValidationException::withMessages(['teacher_assignment_id' => 'Choose one of your subject assignments for this evaluation.']);
        }

        $evaluation = StudentPerformanceEvaluation::create($data + [
            'student_id' => $registration->student_id,
            'teacher_id' => $assignment?->teacher_id ?? $request->user()->teacher_id,
            'created_by' => $request->user()->id,
        ]);
        $audit->log('student_performance_evaluation.created', $evaluation, [], $evaluation->only(['student_id', 'teacher_assignment_id', 'category', 'rating', 'status']));

        return back()->with('success', 'Student performance evaluation saved.');
    }

    /** @return Collection<int, TeacherAssignment> */
    private function availableAssignments(User $user, ?int $academicYearId)
    {
        return TeacherAssignment::query()
            ->with('teacher', 'subject', 'grade', 'section')
            ->where('is_active', true)
            ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->when($user->teacher_id && ! $this->canManageAllAssignments($user), fn (Builder $query) => $query->where('teacher_id', $user->teacher_id))
            ->when(! $user->teacher_id && ! $this->canManageAllAssignments($user), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('teacher_id')
            ->get();
    }

    /** @return Collection<int, StudentRegistration> */
    private function availableRegistrations($assignments, ?int $academicYearId)
    {
        if ($assignments->isEmpty()) {
            return collect();
        }

        return StudentRegistration::query()
            ->with('student', 'grade', 'section')
            ->where('status', 'active')
            ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->where(function (Builder $registrations) use ($assignments): void {
                foreach ($assignments as $assignment) {
                    $registrations->orWhere(function (Builder $registration) use ($assignment): void {
                        $registration->where('grade_id', $assignment->grade_id)
                            ->when($assignment->section_id, fn (Builder $query) => $query->where('section_id', $assignment->section_id));
                    });
                }
            })
            ->orderBy('grade_id')
            ->orderBy('section_id')
            ->with('student')
            ->take(300)
            ->get();
    }

    private function authorizedAssignment(User $user, int $assignmentId): TeacherAssignment
    {
        $assignment = TeacherAssignment::query()->findOrFail($assignmentId);
        abort_unless($this->canManageAllAssignments($user) || ($user->teacher_id && $assignment->teacher_id === $user->teacher_id), 403);

        return $assignment;
    }

    private function canManageAllAssignments(User $user): bool
    {
        return $user->hasPermission('academic_year.manage') || $user->hasPermission('admin.manage');
    }

    private function notifyCourseAudience(TeacherAssignment $assignment, string $title, string $message): void
    {
        Student::query()
            ->with('portalUser')
            ->whereHas('registrations', function (Builder $registrations) use ($assignment): void {
                $registrations->where('status', 'active')
                    ->where('academic_year_id', $assignment->academic_year_id)
                    ->where('grade_id', $assignment->grade_id)
                    ->when($assignment->section_id, fn (Builder $query) => $query->where('section_id', $assignment->section_id));
            })
            ->get()
            ->each(function (Student $student) use ($title, $message): void {
                $student->notify(new StudentPortalNotification($title, $message, route('student-portal.index')));
            });
    }

    /** @return array<string, mixed> */
    private function audienceData(Request $request): array
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'audience' => ['required', Rule::in(['all', 'teachers', 'students', 'guardians', 'grade', 'section'])],
        ]);
        $gradeId = $data['grade_id'] ?? null;
        $sectionId = $data['section_id'] ?? null;
        if ($sectionId && (! $gradeId || ! Section::query()->whereKey($sectionId)->where('grade_id', $gradeId)->exists())) {
            throw ValidationException::withMessages(['section_id' => 'The selected section does not belong to the selected grade.']);
        }
        if (in_array($data['audience'], ['grade', 'section'], true) && ! $gradeId) {
            throw ValidationException::withMessages(['grade_id' => 'Choose a grade for the selected audience.']);
        }
        if ($data['audience'] === 'section' && ! $sectionId) {
            throw ValidationException::withMessages(['section_id' => 'Choose a section for the selected audience.']);
        }

        return $data;
    }
}
