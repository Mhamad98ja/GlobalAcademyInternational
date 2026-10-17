<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\TeacherAssignment;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Services\AuditService;
use App\Services\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TimetableController extends Controller
{
    private const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);
        $yearId = (int) ($filters['academic_year_id'] ?? AcademicYear::query()->where('status', 'open')->value('id'));
        $grades = Grade::query()->with('sections')->orderBy('sort_order')->get();
        $gradeId = $filters['grade_id'] ?? $grades->first()?->id;
        $sectionId = $filters['section_id'] ?? $grades->firstWhere('id', $gradeId)?->sections->first()?->id;
        $periods = TimetablePeriod::query()->where('academic_year_id', $yearId)->orderBy('period_number')->get();
        $periodLessonCounts = $periods->mapWithKeys(fn (TimetablePeriod $period) => [
            $period->id => TimetableEntry::withoutGlobalScopes()
                ->where('branch_id', $period->branch_id)
                ->where('academic_year_id', $period->academic_year_id)
                ->where('period_number', $period->period_number)
                ->count(),
        ]);
        $entries = TimetableEntry::query()->with('teacher', 'subject', 'assignment')
            ->where('academic_year_id', $yearId)
            ->when($gradeId, fn ($query) => $query->where('grade_id', $gradeId))
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->get()
            ->groupBy(fn (TimetableEntry $entry) => $entry->period_number.'-'.strtolower((string) $entry->day_of_week));
        $assignments = TeacherAssignment::query()->with('teacher', 'subject', 'grade', 'section')
            ->where('academic_year_id', $yearId)->where('is_active', true)
            ->when($gradeId, fn ($query) => $query->where('grade_id', $gradeId))
            ->when($sectionId, fn ($query) => $query->where(fn ($scoped) => $scoped->where('section_id', $sectionId)->orWhereNull('section_id')))
            ->get();
        $classEntries = $gradeId && $sectionId
            ? TimetableEntry::query()->where('academic_year_id', $yearId)->where('grade_id', $gradeId)->where('section_id', $sectionId)->get()
            : collect();
        $lessonPeriodNumbers = $periods->where('kind', 'lesson')->pluck('period_number')->all();
        $generationSummary = [
            'configured_assignments' => $assignments->filter(fn (TeacherAssignment $assignment) => $assignment->required_periods_per_week > 0)->count(),
            'missing_lessons' => $assignments->sum(fn (TeacherAssignment $assignment) => max(
                0,
                (int) $assignment->required_periods_per_week - $classEntries->where('teacher_assignment_id', $assignment->id)->count()
            )),
            'open_slots' => max(0, count(self::DAYS) * count($lessonPeriodNumbers) - $classEntries
                ->filter(fn (TimetableEntry $entry) => in_array(strtolower((string) $entry->day_of_week), self::DAYS, true)
                    && in_array($entry->period_number, $lessonPeriodNumbers, true))
                ->unique(fn (TimetableEntry $entry) => strtolower((string) $entry->day_of_week).'-'.$entry->period_number)
                ->count()),
        ];

        return view('timetable.index', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => $grades,
            'periods' => $periods,
            'periodLessonCounts' => $periodLessonCounts,
            'entries' => $entries,
            'assignments' => $assignments,
            'generationSummary' => $generationSummary,
            'filters' => $filters + ['academic_year_id' => $yearId, 'grade_id' => $gradeId, 'section_id' => $sectionId],
            'days' => self::DAYS,
        ]);
    }

    public function storePeriod(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'period_number' => ['required', 'integer', 'min:1', 'max:30'],
            'label' => ['required', 'string', 'max:80'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'kind' => ['required', Rule::in(['lesson', 'break', 'lunch', 'special'])],
        ]);
        $period = TimetablePeriod::updateOrCreate(['academic_year_id' => $data['academic_year_id'], 'period_number' => $data['period_number']], $data);
        $audit->log('timetable_period.saved', $period, [], $period->only(['academic_year_id', 'period_number', 'label', 'kind']));

        return back()->with('success', 'Timetable period saved.');
    }

    public function destroyPeriod(TimetablePeriod $timetablePeriod, AuditService $audit): RedirectResponse
    {
        $removed = DB::transaction(function () use ($timetablePeriod, $audit): int {
            $period = TimetablePeriod::query()->whereKey($timetablePeriod->id)->lockForUpdate()->firstOrFail();
            $entries = TimetableEntry::withoutGlobalScopes()
                ->where('branch_id', $period->branch_id)
                ->where('academic_year_id', $period->academic_year_id)
                ->where('period_number', $period->period_number)
                ->lockForUpdate()
                ->get();

            if (TeacherSubstitution::withoutGlobalScopes()->whereIn('timetable_entry_id', $entries->pluck('id'))->exists()) {
                throw ValidationException::withMessages([
                    'period' => 'This period has lessons with substitution records. Resolve those records before deleting the entire period.',
                ]);
            }

            foreach ($entries as $entry) {
                $audit->log('timetable_entry.deleted', $entry, $entry->only(['teacher_assignment_id', 'day_of_week', 'period_number']), []);
                $entry->delete();
            }

            $audit->log('timetable_period.deleted', $period, $period->only(['academic_year_id', 'period_number', 'label', 'starts_at', 'ends_at', 'kind']), []);
            $period->delete();

            return $entries->count();
        });

        return back()->with('success', 'Timetable period and '.$removed.' scheduled lesson'.($removed === 1 ? '' : 's').' removed.');
    }

    public function generate(Request $request, TimetableService $timetable, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);
        $periods = TimetablePeriod::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('kind', 'lesson')
            ->orderBy('period_number')
            ->get();
        if ($periods->isEmpty()) {
            throw ValidationException::withMessages(['period_id' => 'Add lesson periods before generating a timetable.']);
        }

        $assignments = TeacherAssignment::query()
            ->with('teacher', 'subject')
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('grade_id', $data['grade_id'])
            ->where(fn ($query) => $query->where('section_id', $data['section_id'])->orWhereNull('section_id'))
            ->where('is_active', true)
            ->where('required_periods_per_week', '>', 0)
            ->orderByDesc('required_periods_per_week')
            ->orderBy('teacher_id')
            ->get();
        $entries = TimetableEntry::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->get();
        $classEntries = $entries->where('grade_id', $data['grade_id'])->where('section_id', $data['section_id']);
        $occupiedClass = $classEntries->mapWithKeys(fn (TimetableEntry $entry) => [strtolower((string) $entry->day_of_week).'-'.$entry->period_number => true])->all();
        $occupiedTeachers = $entries->mapWithKeys(fn (TimetableEntry $entry) => [$entry->teacher_id.'-'.strtolower((string) $entry->day_of_week).'-'.$entry->period_number => true])->all();
        $assignmentCounts = $classEntries->groupBy('teacher_assignment_id')->map->count()->all();
        $teacherCounts = $entries->groupBy('teacher_id')->map->count()->all();
        $classDayLoads = $classEntries->groupBy(fn (TimetableEntry $entry) => strtolower((string) $entry->day_of_week))->map->count()->all();
        if ($assignments->isEmpty()) {
            return back()->with('error', 'No weekly lessons are configured for this class. Set the required periods per week in Teacher Subject Assignments first.');
        }
        $missingLessons = $assignments->sum(fn (TeacherAssignment $assignment) => max(0,
            (int) $assignment->required_periods_per_week - (int) ($assignmentCounts[$assignment->id] ?? 0)
        ));
        if ($missingLessons === 0) {
            return back()->with('success', 'All required weekly lessons are already scheduled for this class.');
        }
        $hasOpenSlot = false;
        foreach (self::DAYS as $day) {
            foreach ($periods as $period) {
                if (! isset($occupiedClass[$day.'-'.$period->period_number])) {
                    $hasOpenSlot = true;
                    break 2;
                }
            }
        }
        if (! $hasOpenSlot) {
            return back()->with('error', 'No empty lesson periods remain for this class. Move or remove a lesson, or add more lesson periods.');
        }
        $assignmentDayLoads = [];
        $created = 0;
        $unscheduled = [];

        foreach ($assignments as $assignment) {
            $remaining = max(0, (int) $assignment->required_periods_per_week - (int) ($assignmentCounts[$assignment->id] ?? 0));
            for ($lesson = 0; $lesson < $remaining; $lesson++) {
                $candidates = [];
                foreach (self::DAYS as $day) {
                    foreach ($periods as $period) {
                        $slotKey = $day.'-'.$period->period_number;
                        if (isset($occupiedClass[$slotKey]) || isset($occupiedTeachers[$assignment->teacher_id.'-'.$slotKey])) {
                            continue;
                        }
                        if ($assignment->weekly_period_limit !== null && ($teacherCounts[$assignment->teacher_id] ?? 0) >= $assignment->weekly_period_limit) {
                            continue;
                        }
                        $candidates[] = [
                            'day' => $day,
                            'period' => $period->period_number,
                            'score' => (($classDayLoads[$day] ?? 0) * 10)
                                + (($assignmentDayLoads[$assignment->id][$day] ?? 0) * 100)
                                + (($teacherCounts[$assignment->teacher_id] ?? 0) * 0.01),
                        ];
                    }
                }
                if ($candidates === []) {
                    $unscheduled[] = ($assignment->subject?->name ?? 'Subject').' · '.($assignment->teacher?->full_name ?? 'Teacher');
                    break;
                }
                usort($candidates, fn (array $left, array $right): int => $left['score'] <=> $right['score'] ?: $left['period'] <=> $right['period']);
                $slot = $candidates[0];
                try {
                    $entry = $timetable->create($assignment, [
                        'section_id' => $data['section_id'],
                        'day_of_week' => $slot['day'],
                        'period_number' => $slot['period'],
                    ]);
                } catch (ValidationException) {
                    $unscheduled[] = ($assignment->subject?->name ?? 'Subject').' · '.($assignment->teacher?->full_name ?? 'Teacher');
                    break;
                }
                $slotKey = $slot['day'].'-'.$slot['period'];
                $occupiedClass[$slotKey] = true;
                $occupiedTeachers[$assignment->teacher_id.'-'.$slotKey] = true;
                $assignmentCounts[$assignment->id] = ($assignmentCounts[$assignment->id] ?? 0) + 1;
                $teacherCounts[$assignment->teacher_id] = ($teacherCounts[$assignment->teacher_id] ?? 0) + 1;
                $classDayLoads[$slot['day']] = ($classDayLoads[$slot['day']] ?? 0) + 1;
                $assignmentDayLoads[$assignment->id][$slot['day']] = ($assignmentDayLoads[$assignment->id][$slot['day']] ?? 0) + 1;
                $audit->log('timetable_entry.generated', $entry, [], $entry->only(['teacher_assignment_id', 'day_of_week', 'period_number']));
                $created++;
            }
        }

        $message = $created.' lesson card'.($created === 1 ? '' : 's').' scheduled for the selected class.';
        if ($unscheduled !== []) {
            $message .= ' '.count($unscheduled).' lesson'.(count($unscheduled) === 1 ? '' : 's').' could not be placed because of timetable conflicts or limits.';
        }

        return back()->with('success', $message);
    }

    public function store(Request $request, TimetableService $timetable, AuditService $audit): RedirectResponse|JsonResponse
    {
        $data = $this->entryData($request);
        $assignment = TeacherAssignment::query()->findOrFail($data['teacher_assignment_id']);
        $entry = $timetable->create($assignment, $data);
        $entry->load('teacher', 'subject');
        $audit->log('timetable_entry.created', $entry, [], $entry->only(['teacher_assignment_id', 'day_of_week', 'period_number', 'room']));

        if ($request->expectsJson()) {
            return response()->json(['entry' => ['id' => $entry->public_id, 'teacher' => $entry->teacher->full_name, 'subject' => $entry->subject->name]], 201);
        }

        return back()->with('success', 'Timetable lesson scheduled.');
    }

    public function update(Request $request, TimetableEntry $timetableEntry, TimetableService $timetable, AuditService $audit): RedirectResponse|JsonResponse
    {
        $data = $this->entryData($request);
        $assignment = TeacherAssignment::query()->findOrFail($data['teacher_assignment_id']);
        $old = $timetableEntry->only(['teacher_assignment_id', 'day_of_week', 'period_number', 'room']);
        $entry = $timetable->update($timetableEntry, $assignment, $data);
        $audit->log('timetable_entry.updated', $entry, $old, $entry->only(array_keys($old)));

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Timetable lesson updated.');
    }

    public function destroy(Request $request, TimetableEntry $timetableEntry, AuditService $audit): RedirectResponse|JsonResponse
    {
        $audit->log('timetable_entry.deleted', $timetableEntry, $timetableEntry->only(['teacher_assignment_id', 'day_of_week', 'period_number']), []);
        $timetableEntry->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Timetable lesson removed.');
    }

    /** @return array{teacher_assignment_id:int,day_of_week:string,period_number:int,room?:string|null} */
    private function entryData(Request $request): array
    {
        return $request->validate([
            'teacher_assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'day_of_week' => ['required', Rule::in(self::DAYS)],
            'period_number' => ['required', 'integer', 'min:1', 'max:30'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'is_parallel' => ['prohibited'],
            'room' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
