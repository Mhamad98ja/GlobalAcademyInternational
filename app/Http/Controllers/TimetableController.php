<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Services\AuditService;
use App\Services\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TimetableController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

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
        $entries = TimetableEntry::query()->with('teacher', 'subject', 'assignment')
            ->where('academic_year_id', $yearId)
            ->when($gradeId, fn ($query) => $query->where('grade_id', $gradeId))
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->get()
            ->keyBy(fn (TimetableEntry $entry) => $entry->period_number.'-'.$entry->day_of_week);
        $assignments = TeacherAssignment::query()->with('teacher', 'subject', 'grade', 'section')
            ->where('academic_year_id', $yearId)->where('is_active', true)
            ->when($gradeId, fn ($query) => $query->where('grade_id', $gradeId))
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->get();

        return view('timetable.index', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => $grades,
            'periods' => $periods,
            'entries' => $entries,
            'assignments' => $assignments,
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

        return back()->with('success', 'Timetable lesson moved.');
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
            'room' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
