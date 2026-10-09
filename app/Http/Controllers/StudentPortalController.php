<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CourseContent;
use App\Models\Student;
use App\Models\StudentRegistration;
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
            ->with('academicYear', 'grade', 'section')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('academicYear', fn (Builder $years) => $years->where('status', 'open'))
            ->latest('registered_on')
            ->get();

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
                            $scope->where('grade_id', $registration->grade_id)
                                ->where(fn (Builder $section) => $section->whereNull('section_id')->orWhere('section_id', $registration->section_id));
                        });
                    }
                });
            })
            ->orderByRaw('due_on IS NULL')
            ->orderBy('due_on')
            ->latest('published_at')
            ->take(50)
            ->get();

        $assessments = Assessment::query()
            ->with('term', 'subject', 'grade', 'section', 'grades')
            ->where('status', 'open')
            ->when($registrations->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where(function (Builder $query) use ($registrations): void {
                foreach ($registrations as $registration) {
                    $query->orWhere(function (Builder $scope) use ($registration): void {
                        $scope->where('academic_year_id', $registration->academic_year_id)
                            ->where('grade_id', $registration->grade_id)
                            ->where(fn (Builder $section) => $section->whereNull('section_id')->orWhere('section_id', $registration->section_id));
                    });
                }
            })
            ->orderBy('assessment_date')
            ->take(50)
            ->get();

        $publishedGrades = $student->gradeEntries()
            ->with('assessment.subject', 'assessment.term', 'assignment.teacher', 'assignment.grade', 'assignment.section')
            ->where('status', 'published')
            ->latest('published_at')
            ->take(50)
            ->get();

        $notifications = $student->notifications()->latest()->take(20)->get();

        return view('student-portal.index', compact('student', 'registrations', 'contents', 'assessments', 'publishedGrades', 'notifications'));
    }
}
