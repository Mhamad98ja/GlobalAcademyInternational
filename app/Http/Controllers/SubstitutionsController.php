<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherSubstitution;
use App\Models\AcademicYear;
use App\Models\TimetableEntry;
use App\Services\AuditService;
use App\Services\SubstitutionService;
use App\Services\WorkloadService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubstitutionsController extends Controller
{
    public function index(Request $request, SubstitutionService $substitutionService): View
    {
        $filters = $request->validate([
            'substitution_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'recommended', 'assigned', 'completed', 'cancelled'])],
        ]);
        $date = $filters['substitution_date'] ?? today()->toDateString();
        $absentTeachers = TeacherAttendance::query()->with('teacher')->whereDate('attendance_date', $date)->whereIn('status', TeacherAttendance::ABSENCE_STATUSES)->get()->sortBy('teacher.first_name')->values();
        $activeAcademicYearId = AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->value('id');
        $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);
        $absentLessons = TimetableEntry::query()
            ->with('subject', 'grade', 'section')
            ->whereIn('teacher_id', $absentTeachers->pluck('teacher_id'))
            ->where('day_of_week', $dayOfWeek)
            ->when($activeAcademicYearId, fn ($query) => $query->where('academic_year_id', $activeAcademicYearId))
            ->orderBy('period_number')
            ->get()
            ->groupBy('teacher_id');
        $allSubstitutions = $this->substitutionsForDate($date);
        foreach ($absentTeachers as $attendance) {
            $lessonIds = $absentLessons->get($attendance->teacher_id, collect())->pluck('id');
            $coveredLessonIds = $allSubstitutions->where('absent_teacher_id', $attendance->teacher_id)->pluck('timetable_entry_id');
            if ($lessonIds->diff($coveredLessonIds)->isNotEmpty()) {
                $substitutionService->syncForAttendance($attendance);
            }
        }
        if ($absentTeachers->isNotEmpty()) {
            $allSubstitutions = $this->substitutionsForDate($date);
        }

        return view('substitutions.index', [
            'substitutions' => $allSubstitutions->when($filters['status'] ?? null, fn ($rows, $status) => $rows->where('status', $status))->values(),
            'coverageByEntry' => $allSubstitutions->keyBy('timetable_entry_id'),
            'absentTeachers' => $absentTeachers,
            'absentLessons' => $absentLessons,
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('first_name')->get(),
            'date' => $date,
            'filters' => $filters,
        ]);
    }

    private function substitutionsForDate(string $date)
    {
        return TeacherSubstitution::query()
            ->with('entry.subject', 'entry.grade', 'entry.section', 'absentTeacher', 'substituteTeacher')
            ->whereDate('substitution_date', $date)
            ->orderBy('id')
            ->get();
    }

    public function assign(Request $request, TeacherSubstitution $teacherSubstitution, WorkloadService $workload, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'substitute_teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['assigned', 'completed', 'cancelled'])],
        ]);
        $teacher = ! empty($data['substitute_teacher_id']) ? Teacher::findOrFail($data['substitute_teacher_id']) : null;
        if (in_array($data['status'], ['assigned', 'completed'], true) && ! $teacher) {
            throw ValidationException::withMessages(['substitute_teacher_id' => 'Choose a substitute teacher before assigning this lesson.']);
        }
        $teacherSubstitution->load('entry');
        if ($teacher && ! $workload->isAvailable($teacher, Carbon::parse($teacherSubstitution->substitution_date), $teacherSubstitution->entry->period_number, $teacherSubstitution->entry->academic_year_id)) {
            throw ValidationException::withMessages(['substitute_teacher_id' => 'This teacher is not available for the selected timetable period.']);
        }
        $old = $teacherSubstitution->only(['substitute_teacher_id', 'status', 'override_reason']);
        $teacherSubstitution->update($data + ['assigned_by' => $request->user()->id]);
        $audit->log('teacher_substitution.assigned', $teacherSubstitution, $old, $teacherSubstitution->only(array_keys($old)));

        return back()->with('success', 'Substitution assignment saved.');
    }

    public function recommend(TeacherSubstitution $teacherSubstitution, SubstitutionService $substitutions, AuditService $audit): RedirectResponse
    {
        $teacherSubstitution->load('entry');
        $recommendation = $substitutions->recommend($teacherSubstitution->entry, Carbon::parse($teacherSubstitution->substitution_date), $teacherSubstitution->absent_teacher_id);
        $teacherSubstitution->update([
            'substitute_teacher_id' => $recommendation['teacher']?->id,
            'status' => $recommendation['teacher'] ? 'recommended' : 'pending',
            'recommendation_details' => $recommendation['details'],
        ]);
        $audit->log('teacher_substitution.recommended', $teacherSubstitution, [], $recommendation['details']);

        return back()->with('success', 'Fairness recommendation recalculated.');
    }
}
