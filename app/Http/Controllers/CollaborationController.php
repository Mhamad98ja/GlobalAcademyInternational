<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AssessmentModificationRequest;
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
use App\Models\TeacherSubstitution;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\AuditService;
use App\Notifications\StudentPortalNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $activeAcademicTerm = AcademicTerm::activeForYear($activeAcademicYear?->id);
        $section = (string) $request->route('section', $request->query('section', ''));
        $search = trim((string) $request->query('q', ''));
        $gradeId = $request->integer('grade_id') ?: null;
        $studentId = $request->integer('student_id') ?: null;
        $date = $request->date('date');
        $agendaDate = $request->date('agenda_date') ?? today();
        $agendaStart = Carbon::parse($agendaDate)->startOfWeek(Carbon::MONDAY);
        $agendaView = in_array($request->query('view'), ['weekly', 'daily', 'class'], true) ? (string) $request->query('view') : 'weekly';
        $agendaDays = $agendaView === 'daily'
            ? collect([Carbon::parse($agendaDate)])
            : collect(range(0, 4))->map(fn (int $offset) => $agendaStart->copy()->addDays($offset));
        $agendaFilterStart = $agendaDays->first()->copy()->startOfDay();
        $agendaFilterEnd = $agendaDays->last()->copy()->endOfDay();
        $calendarFilters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $calendarDate = Carbon::create(
            (int) ($calendarFilters['year'] ?? today()->year),
            (int) ($calendarFilters['month'] ?? today()->month),
            1,
        );
        $assignments = $this->availableAssignments($user, $activeAcademicYear?->id, $gradeId);
        $evaluationAssignmentId = $request->integer('teacher_assignment_id') ?: null;
        $evaluationAssignments = $assignments
            ->when($evaluationAssignmentId, fn (Collection $items) => $items->where('id', $evaluationAssignmentId)->values());
        $assignmentStudentCounts = $this->assignmentStudentCounts($assignments, $activeAcademicYear?->id);
        $isTeacherAgenda = (bool) $user->teacher_id && ! $this->canManageAllAssignments($user);
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
            ->when($assignments->isNotEmpty(), fn (Builder $query) => $query->whereIn('teacher_assignment_id', $assignments->modelKeys()))
            ->when($assignments->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
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
            ->when($date && $section !== 'homework', fn (Builder $query) => $query->whereDate('due_on', $date))
            ->when($section === 'homework', function (Builder $query) use ($agendaFilterStart, $agendaFilterEnd): void {
                $query->where(function (Builder $items) use ($agendaFilterStart, $agendaFilterEnd): void {
                    $items->whereBetween('due_on', [$agendaFilterStart->toDateString(), $agendaFilterEnd->toDateString()])
                        ->orWhereBetween('available_from', [$agendaFilterStart, $agendaFilterEnd])
                        ->orWhere(function (Builder $published) use ($agendaFilterStart, $agendaFilterEnd): void {
                            $published->whereNull('due_on')->whereBetween('published_at', [$agendaFilterStart, $agendaFilterEnd]);
                        });
                });
            })
            ->latest('published_at')
            ->latest('id')
            ->take($section === 'homework' ? 200 : 15)
            ->get();

        $agendaGrid = [];
        if ($section === 'homework') {
            foreach ($assignments as $assignment) {
                $agendaGrid[$assignment->id] = [];
                foreach ($contents->where('teacher_assignment_id', $assignment->id) as $content) {
                    $contentDate = $content->due_on?->toDateString()
                        ?? $content->available_from?->toDateString()
                        ?? $content->published_at?->toDateString();
                    if ($contentDate) {
                        $agendaGrid[$assignment->id][$contentDate][] = $content;
                    }
                }
            }
        }

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
                ->with('student', 'registration.grade', 'registration.section', 'registration.subjectExemptions', 'assignment.subject', 'teacher')
                ->when($activeAcademicYear, fn (Builder $query) => $query->whereHas('registration', fn (Builder $registrations) => $registrations->where('academic_year_id', $activeAcademicYear->id)))
                ->when($gradeId, fn (Builder $query, int $id) => $query->whereHas('registration', fn (Builder $registrations) => $registrations->where('grade_id', $id)))
                ->when($studentId, fn (Builder $query, int $id) => $query->where('student_id', $id))
                ->when($user->teacher_id && ! $this->canManageAllAssignments($user), fn (Builder $query) => $query->where('teacher_id', $user->teacher_id))
                ->latest('observed_on')
                ->latest('id')
                ->get()
                ->reject(fn ($evaluation) => $evaluation->assignment && ($evaluation->registration?->subjectExemptions->contains('subject_id', $evaluation->assignment->subject_id) ?? false))
                ->take(15)
            : collect();

        $teachers = Teacher::query()
            ->where('is_active', true)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $teachers) => $teachers
                ->whereInsensitiveLike('first_name', $search)
                ->orWhereInsensitiveLike('last_name', $search)
                ->orWhereInsensitiveLike('employee_number', $search)))
            ->orderBy('first_name')->orderBy('last_name')->take(30)->get();
        $messages = SchoolMessage::query()->with('sender')->latest('sent_at')->latest('id')->take(30)->get();
        $assessmentTerms = $activeAcademicTerm ? collect([$activeAcademicTerm]) : collect();
        $isEvaluationAdministrator = $this->canManageAllAssignments($user);
        $submittedEvaluations = $section === 'modification-requests'
            ? Assessment::query()
                ->with('term', 'assignment.teacher', 'assignment.subject', 'assignment.grade', 'assignment.section', 'modificationRequests')
                ->where('review_status', 'submitted')
                ->when($activeAcademicYear, fn (Builder $query) => $query->where('academic_year_id', $activeAcademicYear->id))
                ->when($activeAcademicTerm, fn (Builder $query) => $query->where('academic_term_id', $activeAcademicTerm->id), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->when(! $isEvaluationAdministrator, fn (Builder $query) => $query->whereHas('assignment', fn (Builder $assignments) => $assignments->where('teacher_id', $user->teacher_id)))
                ->latest('created_at')->take(100)->get()
            : collect();
        $modificationRequests = $section === 'modification-requests'
            ? AssessmentModificationRequest::query()
                ->with('requestedBy', 'reviewedBy', 'assessment.term', 'assessment.assignment.teacher', 'assessment.assignment.subject', 'assessment.assignment.grade', 'assessment.assignment.section')
                ->when($activeAcademicTerm, fn (Builder $query) => $query->whereHas('assessment', fn (Builder $assessment) => $assessment->where('academic_term_id', $activeAcademicTerm->id)), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->when(! $isEvaluationAdministrator, fn (Builder $query) => $query->where('requested_by', $user->id))
                ->latest('created_at')->take(100)->get()
            : collect();

        $todayLessons = $activeAcademicYear
            ? TimetableEntry::query()
                ->with('subject', 'grade', 'section', 'teacher')
                ->where('academic_year_id', $activeAcademicYear->id)
                ->where('day_of_week', strtolower(today()->englishDayOfWeek))
                ->when($assignments->isNotEmpty(), fn (Builder $query) => $query->whereIn('teacher_assignment_id', $assignments->modelKeys()))
                ->when($assignments->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->orderBy('period_number')
                ->get()
            : collect();
        $todaySubstitutions = TeacherSubstitution::query()
            ->with('entry.subject', 'entry.grade', 'entry.section', 'absentTeacher', 'substituteTeacher')
            ->whereDate('substitution_date', today())
            ->when($isTeacherAgenda, fn (Builder $query) => $query->where(fn (Builder $substitutions) => $substitutions
                ->where('absent_teacher_id', $user->teacher_id)
                ->orWhere('substitute_teacher_id', $user->teacher_id)))
            ->orderBy('id')
            ->take(12)
            ->get();
        $dashboardTasks = collect([
            ['permission' => 'collaboration.agenda.view', 'label' => 'Open subject agenda', 'description' => 'Review lessons, homework, resources, and due dates.', 'route' => 'collaboration.index', 'params' => ['section' => 'homework'], 'tone' => 'blue'],
            ['permission' => 'evaluation.dashboard.view', 'label' => 'Open evaluation dashboard', 'description' => 'See the current term assessment grid and status.', 'route' => 'collaboration.index', 'params' => ['section' => 'evaluation-dashboard'], 'tone' => 'violet'],
            ['permission' => 'attendance.teacher.view', 'label' => 'Record teacher attendance', 'description' => 'Mark today’s presence and generate substitution coverage.', 'route' => 'attendance.teachers', 'params' => [], 'tone' => 'amber'],
            ['permission' => 'substitutions.view', 'label' => 'Manage substitutions', 'description' => 'Assign or confirm today’s temporary cover periods.', 'route' => 'substitutions.index', 'params' => [], 'tone' => 'green'],
        ])->filter(fn (array $task): bool => $user->hasPermission($task['permission']))->values();

        $evaluationDashboardAssessments = $section === 'evaluation-dashboard' && $evaluationAssignments->isNotEmpty()
            ? Assessment::query()
                ->with('term')
                ->whereIn('teacher_assignment_id', $evaluationAssignments->modelKeys())
                ->when($activeAcademicYear, fn (Builder $query) => $query->where('academic_year_id', $activeAcademicYear->id))
                ->when($activeAcademicTerm, fn (Builder $query) => $query->where('academic_term_id', $activeAcademicTerm->id), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->orderBy('academic_term_id')
                ->orderBy('assessment_date')
                ->orderBy('id')
                ->get()
                ->map(function (Assessment $assessment): array {
                    $termLabel = $assessment->term?->name ?: 'No term assigned';

                    return [
                        'id' => $assessment->id,
                        'teacher_assignment_id' => $assessment->teacher_assignment_id,
                        'name' => $assessment->name,
                        'type' => $assessment->type,
                        'type_key' => str($assessment->type)->snake()->toString(),
                        'type_label' => str($assessment->type)->headline()->toString(),
                        'column_key' => str($termLabel)->slug()->toString(),
                        'column_label' => $termLabel,
                        'term_label' => $termLabel,
                        'status_key' => str($assessment->review_status ?: $assessment->status)->slug()->toString(),
                        'status_label' => str($assessment->review_status ?: $assessment->status)->headline()->toString(),
                        'assessment_date' => $assessment->assessment_date?->format('d M Y'),
                    ];
                })
            : collect();
        $evaluationColumns = $evaluationDashboardAssessments
            ->map(fn (array $item): array => ['key' => $item['column_key'], 'label' => $item['column_label']])
            ->unique('key')->values();
        $evaluationTypes = $evaluationDashboardAssessments
            ->map(fn (array $item): array => ['key' => $item['type_key'], 'label' => $item['type_label']])
            ->unique('key')->values();
        if ($evaluationColumns->isEmpty()) {
            $evaluationColumns = collect([['key' => 'unassigned', 'label' => 'Unassigned']]);
        }
        if ($evaluationTypes->isEmpty()) {
            $evaluationTypes = collect([['key' => 'project', 'label' => 'Project'], ['key' => 'quiz', 'label' => 'Quiz']]);
        }

        return view($section === '' ? 'collaboration.dashboard' : 'collaboration.index', [
            'activeAcademicYear' => $activeAcademicYear,
            'activeAcademicTerm' => $activeAcademicTerm,
            'grades' => Grade::query()->with('sections')->orderBy('sort_order')->get(),
            'assignments' => $assignments,
            'evaluationAssignments' => $evaluationAssignments,
            'evaluationAssignmentId' => $evaluationAssignmentId,
            'evaluationDashboardAssessments' => $evaluationDashboardAssessments,
            'evaluationColumns' => $evaluationColumns,
            'evaluationTypes' => $evaluationTypes,
            'registrations' => $canManagePerformance ? $this->availableRegistrations($assignments, $activeAcademicYear?->id) : collect(),
            'announcements' => $announcements,
            'contents' => $contents,
            'events' => $events,
            'evaluations' => $evaluations,
            'teachers' => $teachers,
            'messages' => $messages,
            'submittedEvaluations' => $submittedEvaluations,
            'modificationRequests' => $modificationRequests,
            'isEvaluationAdministrator' => $isEvaluationAdministrator,
            'assessmentTerms' => $assessmentTerms,
            'canManage' => $canManage,
            'canManagePerformance' => $canManagePerformance,
            'canViewPerformance' => $canViewPerformance,
            'section' => $section,
            'search' => $search,
            'selectedGradeId' => $gradeId,
            'selectedDate' => $date?->toDateString(),
            'calendarDate' => $calendarDate,
            'agendaDate' => $agendaDate,
            'agendaStart' => $agendaStart,
            'agendaDays' => $agendaDays,
            'agendaView' => $agendaView,
            'agendaGrid' => $agendaGrid,
            'assignmentStudentCounts' => $assignmentStudentCounts,
            'isTeacherAgenda' => $isTeacherAgenda,
            'todayLessons' => $todayLessons,
            'todaySubstitutions' => $todaySubstitutions,
            'dashboardTasks' => $dashboardTasks,
        ]);
    }

    public function storeModificationRequest(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'assessment_id' => ['required', 'integer', 'exists:assessments,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $assessment = Assessment::query()->with('assignment')->findOrFail($data['assessment_id']);
        $user = $request->user();
        abort_unless($this->canManageAllAssignments($user) || ($user->teacher_id && $assessment->assignment?->teacher_id === $user->teacher_id), 403);
        abort_unless($assessment->review_status === 'submitted', 422, 'Only submitted evaluations can be sent for modification.');
        if (AssessmentModificationRequest::query()->where('assessment_id', $assessment->id)->where('requested_by', $user->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['assessment_id' => 'A modification request is already pending for this evaluation.']);
        }

        $modificationRequest = AssessmentModificationRequest::create([
            'assessment_id' => $assessment->id,
            'requested_by' => $user->id,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);
        $audit->log('assessment.modification_requested', $modificationRequest, [], $modificationRequest->only(['assessment_id', 'requested_by', 'reason', 'status']));

        return redirect()->route('collaboration.section', ['section' => 'modification-requests'])->with('success', 'Modification request sent to administration.');
    }

    public function approveModificationRequest(AssessmentModificationRequest $modificationRequest, AuditService $audit): RedirectResponse
    {
        abort_unless($modificationRequest->status === 'pending', 422, 'Only pending modification requests can be approved.');
        $modificationRequest->update(['status' => 'approved', 'reviewed_by' => request()->user()->id, 'reviewed_at' => now(), 'response' => null]);
        $audit->log('assessment.modification_request_approved', $modificationRequest, [], $modificationRequest->only(['assessment_id', 'status', 'reviewed_by']));

        return back()->with('success', 'The teacher can now modify this submitted evaluation.');
    }

    public function rejectModificationRequest(Request $request, AssessmentModificationRequest $modificationRequest, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:2000']]);
        abort_unless($modificationRequest->status === 'pending', 422, 'Only pending modification requests can be rejected.');
        $modificationRequest->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'response' => $data['response']]);
        $audit->log('assessment.modification_request_rejected', $modificationRequest, [], $modificationRequest->only(['assessment_id', 'status', 'reviewed_by', 'response']));

        return back()->with('success', 'Modification request returned to the teacher.');
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
            'content_type' => ['required', Rule::in(['lesson', 'homework', 'assignment', 'assessment', 'document', 'link', 'video', 'announcement', 'other'])],
            'resource_url' => ['nullable', 'url', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,jpg,jpeg,png', 'max:20480'],
            'available_from' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'disable_response' => ['nullable', 'boolean'],
        ]);
        $this->ensureAgendaDates($data);
        $attachment = $this->storeCourseAttachment($request);
        unset($data['attachment']);
        $assignment = $this->authorizedAssignment($request->user(), (int) $data['teacher_assignment_id']);
        if (! $assignment->is_active) {
            throw ValidationException::withMessages(['teacher_assignment_id' => 'Select an active subject assignment. Inactive assignments cannot receive new agenda items.']);
        }
        $targets = collect([$assignment]);
        if ($request->boolean('copy_to_sections')) {
            $targets = TeacherAssignment::query()
                ->where('branch_id', $assignment->branch_id)
                ->where('academic_year_id', $assignment->academic_year_id)
                ->where('teacher_id', $assignment->teacher_id)
                ->where('subject_id', $assignment->subject_id)
                ->where('grade_id', $assignment->grade_id)
                ->where('is_active', true)
                ->orderBy('section_id')
                ->get();
        }
        $common = collect($data)->except('teacher_assignment_id')->all() + [
            'published_at' => $request->boolean('publish_now') ? now() : null,
            'created_by' => $request->user()->id,
            'disable_response' => $request->boolean('disable_response', true),
        ];
        if ($attachment) {
            $common += $attachment;
        }
        $contents = DB::transaction(fn () => $targets->map(fn (TeacherAssignment $target) => CourseContent::create($common + ['teacher_assignment_id' => $target->id])));
        $contents->each(function (CourseContent $content) use ($audit): void {
            $assignment = $content->assignment;
            if ($content->published_at) {
                $this->notifyCourseAudience($assignment, $content->title, 'A new '.$content->content_type.' was published for '.$assignment->subject?->name.'.');
            }
            $audit->log('course_content.created', $content, [], $content->only(['teacher_assignment_id', 'title', 'content_type', 'published_at']));
        });
        $content = $contents->first();

        return back()->with('success', $content->published_at
            ? 'Subject agenda item published'.($contents->count() > 1 ? ' to all selected sections.' : '.')
            : 'Subject agenda item saved as a draft'.($contents->count() > 1 ? ' for all selected sections.' : '.'));
    }

    public function updateCourseContent(Request $request, CourseContent $courseContent, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'teacher_assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['nullable', 'string', 'max:10000'],
            'content_type' => ['required', Rule::in(['lesson', 'homework', 'assignment', 'assessment', 'document', 'link', 'video', 'announcement', 'other'])],
            'resource_url' => ['nullable', 'url', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,jpg,jpeg,png', 'max:20480'],
            'available_from' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'disable_response' => ['nullable', 'boolean'],
        ]);
        $this->ensureAgendaDates($data);
        $attachment = $this->storeCourseAttachment($request);
        unset($data['attachment']);
        $assignment = $this->authorizedAssignment($request->user(), (int) $data['teacher_assignment_id']);
        $wasPublished = $courseContent->published_at !== null;
        if ($attachment && $courseContent->attachment_path) {
            Storage::disk('local')->delete($courseContent->attachment_path);
        }
        $courseContent->update(collect($data)->except('teacher_assignment_id')->all() + ['teacher_assignment_id' => $assignment->id, 'disable_response' => $request->boolean('disable_response', true), 'published_at' => $wasPublished || $request->boolean('publish_now') ? ($courseContent->published_at ?? now()) : null] + ($attachment ?: []));
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
            'meeting_platform' => ['nullable', Rule::in(['zoom', 'google_meet', 'teams', 'other'])],
            'meeting_url' => ['nullable', 'url', 'max:2000'],
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
            'category' => ['required', Rule::in(['participation', 'behavior', 'effort', 'homework', 'engagement', 'incident', 'other'])],
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
        if ($assignment && $registration->subjectExemptions()->where('subject_id', $assignment->subject_id)->exists()) {
            throw ValidationException::withMessages(['teacher_assignment_id' => 'This student is exempt from the selected subject. Use general follow-up if the observation is not subject-specific.']);
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
    private function availableAssignments(User $user, ?int $academicYearId, ?int $gradeId = null)
    {
        return TeacherAssignment::query()
            ->with('teacher', 'subject', 'grade', 'section')
            ->where('is_active', true)
            ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->when($gradeId, fn (Builder $query) => $query->where('grade_id', $gradeId))
            ->when($user->teacher_id && ! $this->canManageAllAssignments($user), fn (Builder $query) => $query->where('teacher_id', $user->teacher_id))
            ->when(! $user->teacher_id && ! $this->canManageAllAssignments($user), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('teacher_id')
            ->get();
    }

    /** @param Collection<int, TeacherAssignment> $assignments @return array<int, int> */
    private function assignmentStudentCounts(Collection $assignments, ?int $academicYearId): array
    {
        if ($assignments->isEmpty()) {
            return [];
        }

        $counts = StudentRegistration::query()
            ->select('grade_id', 'section_id', DB::raw('COUNT(*) as students_count'))
            ->where('status', 'active')
            ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->whereIn('grade_id', $assignments->pluck('grade_id')->unique()->values())
            ->groupBy('grade_id', 'section_id')
            ->get();

        return $assignments->mapWithKeys(function (TeacherAssignment $assignment) use ($counts): array {
            $matchingCounts = $counts->where('grade_id', $assignment->grade_id);
            if ($assignment->section_id) {
                $matchingCounts = $matchingCounts->where('section_id', $assignment->section_id);
            }

            return [$assignment->id => (int) $matchingCounts->sum('students_count')];
        })->all();
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
        return $user->hasPermission('academic_year.manage')
            || $user->hasPermission('admin.manage')
            || ($user->hasRole('employee') && $user->hasPermission('collaboration.manage'));
    }

    /** @param array<string, mixed> $data */
    private function ensureAgendaDates(array $data): void
    {
        if (! empty($data['available_from']) && ! empty($data['due_on']) && Carbon::parse((string) $data['due_on'])->lt(Carbon::parse((string) $data['available_from'])->startOfDay())) {
            throw ValidationException::withMessages(['due_on' => 'The due date cannot be before the publication date.']);
        }
    }

    private function notifyCourseAudience(TeacherAssignment $assignment, string $title, string $message): void
    {
        StudentRegistration::query()
            ->with('student')
            ->where('status', 'active')
            ->where('academic_year_id', $assignment->academic_year_id)
            ->where('grade_id', $assignment->grade_id)
            ->when($assignment->section_id, fn (Builder $query) => $query->where('section_id', $assignment->section_id))
            ->whereDoesntHave('subjectExemptions', fn (Builder $query) => $query->where('subject_id', $assignment->subject_id))
            ->get()
            ->each(function (StudentRegistration $registration) use ($assignment, $title, $message): void {
                $registration->student?->notify(new StudentPortalNotification($title, $message, route('student-portal.index'), $assignment->subject_id, $registration->id));
            });
    }

    private function storeCourseAttachment(Request $request): ?array
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');

        return [
            'attachment_path' => $file->store('course-content', 'local'),
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getClientMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    public function downloadCourseContent(Request $request, CourseContent $courseContent)
    {
        $user = $request->user();
        if ($user->student_id) {
            $student = Student::query()->findOrFail($user->student_id);
            $assignment = $courseContent->assignment;
            $registrationQuery = $student->registrations()->where('status', 'active')->whereHas('academicYear', fn (Builder $years) => $years->where('status', 'open'))->where('academic_year_id', $assignment->academic_year_id)->where('grade_id', $assignment->grade_id);
            if ($assignment->section_id) {
                $registrationQuery->where('section_id', $assignment->section_id);
            }
            $registrationQuery->whereDoesntHave('subjectExemptions', fn (Builder $query) => $query->where('subject_id', $assignment->subject_id));
            abort_unless($courseContent->published_at && $courseContent->published_at->isPast() && $registrationQuery->exists(), 403);
        } else {
            $this->authorizedAssignment($user, (int) $courseContent->teacher_assignment_id);
        }
        abort_unless($courseContent->attachment_path && Storage::disk('local')->exists($courseContent->attachment_path), 404);

        $filename = $courseContent->attachment_name ?: basename($courseContent->attachment_path);
        if (strtolower((string) $courseContent->attachment_mime) === 'application/pdf') {
            return response()->file(Storage::disk('local')->path($courseContent->attachment_path), [
                'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
            ]);
        }

        return Storage::disk('local')->download($courseContent->attachment_path, $filename);
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
