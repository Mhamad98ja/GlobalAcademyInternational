<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CourseContent;
use App\Models\SchoolAnnouncement;
use App\Models\SchoolMessageRecipient;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\SchoolEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->student_id, 403, 'This account is not linked to a student portal profile.');

        $student = Student::query()->with('portalUser')->findOrFail($user->student_id);
        $registrations = StudentRegistration::query()
            ->with('academicYear', 'grade', 'section', 'subjectExemptions')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('academicYear', fn (Builder $years) => $years->where('status', 'open'))
            ->latest('registered_on')
            ->get();
        $registrationHistory = StudentRegistration::query()
            ->with('academicYear', 'grade', 'section')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest('registered_on')
            ->latest('id')
            ->get();
        $exemptions = $student->subjectExemptions()->get();
        $isExempt = static fn (int $registrationId, ?int $subjectId): bool => $subjectId !== null
            && $exemptions->contains(fn ($exemption) => $exemption->student_registration_id === $registrationId && $exemption->subject_id === $subjectId);
        $canSeeSubject = static function (int $yearId, int $gradeId, ?int $sectionId, ?int $subjectId) use ($registrations, $isExempt): bool {
            return $registrations->contains(fn (StudentRegistration $registration) => $registration->academic_year_id === $yearId
                && $registration->grade_id === $gradeId
                && ($sectionId === null || $sectionId === $registration->section_id)
                && ! $isExempt($registration->id, $subjectId));
        };

        $contents = CourseContent::query()
            ->with('assignment.teacher', 'assignment.subject', 'assignment.grade', 'assignment.section')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->when($registrations->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereHas('assignment', function (Builder $assignments) use ($registrations): void {
                $assignments->where('is_active', true)->where(function (Builder $scopes) use ($registrations): void {
                    foreach ($registrations as $registration) {
                        $scopes->orWhere(function (Builder $scope) use ($registration): void {
                            $scope->where('academic_year_id', $registration->academic_year_id)
                                ->where('grade_id', $registration->grade_id)
                                ->where(fn (Builder $section) => $section->whereNull('section_id')->orWhere('section_id', $registration->section_id));
                        });
                    }
                });
            })
            ->orderByRaw('due_on IS NULL')
            ->orderBy('due_on')
            ->latest('published_at')
            ->get()
            ->filter(fn (CourseContent $content) => $content->assignment && $canSeeSubject($content->assignment->academic_year_id, $content->assignment->grade_id, $content->assignment->section_id, $content->assignment->subject_id))
            ->take(50);

        $assessments = Assessment::query()
            ->with('term', 'assignment.teacher', 'assignment.subject', 'assignment.grade', 'assignment.section', 'subject', 'grade', 'section', 'grades')
            ->where('status', 'open')
            ->when($registrations->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where(function (Builder $query) use ($registrations): void {
                foreach ($registrations as $registration) {
                    $query->orWhere(function (Builder $scope) use ($registration): void {
                        $scope->where('academic_year_id', $registration->academic_year_id)
                            ->where(function (Builder $assignmentScope) use ($registration): void {
                                $assignmentScope
                                    ->whereHas('assignment', function (Builder $assignments) use ($registration): void {
                                        $assignments->where('is_active', true)
                                            ->where('academic_year_id', $registration->academic_year_id)
                                            ->where('grade_id', $registration->grade_id)
                                            ->where(fn (Builder $section) => $section->whereNull('section_id')->orWhere('section_id', $registration->section_id));
                                    })
                                    ->orWhere(function (Builder $legacy) use ($registration): void {
                                        $legacy->whereNull('teacher_assignment_id')
                                            ->where('grade_id', $registration->grade_id)
                                            ->where(fn (Builder $section) => $section->whereNull('section_id')->orWhere('section_id', $registration->section_id));
                                    });
                            });
                    });
                }
            })
            ->orderBy('assessment_date')
            ->get()
            ->filter(fn (Assessment $assessment) => $canSeeSubject($assessment->academic_year_id, $assessment->grade_id ?? $assessment->assignment?->grade_id, $assessment->section_id ?? $assessment->assignment?->section_id, $assessment->subject_id ?? $assessment->assignment?->subject_id))
            ->take(50);

        $announcements = SchoolAnnouncement::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
            ->when($registrations->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where(function (Builder $query) use ($registrations): void {
                $query->whereIn('audience', ['all', 'students'])
                    ->orWhere(function (Builder $scoped) use ($registrations): void {
                        $scoped->whereIn('audience', ['grade', 'section'])
                            ->where(function (Builder $matches) use ($registrations): void {
                                foreach ($registrations as $registration) {
                                    $matches->orWhere(function (Builder $scope) use ($registration): void {
                                        $scope->where('grade_id', $registration->grade_id)
                                            ->where(function (Builder $audience) use ($registration): void {
                                                $audience->where('audience', 'grade')
                                                    ->orWhere(fn (Builder $section) => $section->where('audience', 'section')->where('section_id', $registration->section_id));
                                            });
                                    });
                                }
                            });
                    });
            })
            ->latest('published_at')
            ->take(30)
            ->get();

        $events = SchoolEvent::query()
            ->where('starts_at', '>=', now()->startOfDay())
            ->when($registrations->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where(fn (Builder $query) => $query->whereIn('audience', ['all', 'students'])
                ->orWhere(function (Builder $scoped) use ($registrations): void {
                    $scoped->whereIn('audience', ['grade', 'section'])->where(function (Builder $matches) use ($registrations): void {
                        foreach ($registrations as $registration) {
                            $matches->orWhere(function (Builder $scope) use ($registration): void {
                                $scope->where('grade_id', $registration->grade_id)->where(fn (Builder $audience) => $audience->where('audience', 'grade')->orWhere(fn (Builder $section) => $section->where('audience', 'section')->where('section_id', $registration->section_id)));
                            });
                        }
                    });
                }))
            ->orderBy('starts_at')->take(20)->get();

        $publishedGrades = $student->gradeEntries()
            ->with('assessment.assignment.teacher', 'assessment.assignment.subject', 'assessment.assignment.grade', 'assessment.assignment.section', 'assessment.subject', 'assessment.term', 'assignment.teacher', 'assignment.grade', 'assignment.section')
            ->where('status', 'published')
            ->latest('published_at')
            ->get()
            ->reject(fn ($entry) => $isExempt($entry->student_registration_id, $entry->assessment?->subject_id ?? $entry->assessment?->assignment?->subject_id ?? $entry->assignment?->subject_id))
            ->take(50);

        $notifications = $student->notifications()->latest()->get()
            ->reject(fn ($notification) => $isExempt((int) ($notification->data['student_registration_id'] ?? 0), isset($notification->data['subject_id']) ? (int) $notification->data['subject_id'] : null))
            ->take(20);

        $messages = SchoolMessageRecipient::query()
            ->where('recipient_id', $user->id)
            ->where('is_archived', false)
            ->with(['message' => fn ($messages) => $messages->withoutGlobalScopes()->with('sender')])
            ->latest('id')
            ->take(12)
            ->get();
        $unreadMessages = $messages->whereNull('read_at')->count();

        return view('student-portal.index', compact('student', 'registrations', 'registrationHistory', 'contents', 'assessments', 'publishedGrades', 'notifications', 'announcements', 'events', 'messages', 'unreadMessages'));
    }
}
