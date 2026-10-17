@extends('layouts.app', ['title' => 'Timetable'])

@section('content')
@php
    $canManageTimetable = auth()->user()->hasPermission('timetable.manage');
    $hasSelectedClass = filled($filters['grade_id'] ?? null) && filled($filters['section_id'] ?? null);
    $canGenerate = $hasSelectedClass && $periods->contains('kind', 'lesson')
        && $generationSummary['configured_assignments'] > 0
        && $generationSummary['missing_lessons'] > 0
        && $generationSummary['open_slots'] > 0;
@endphp
<div class="page-header"><div><h1 class="page-title">Graphical timetable</h1><p class="page-subtitle">Set period times, assign subjects and teachers with weekly lesson counts, then generate or adjust the class schedule.</p>@if (auth()->user()->hasPermission('academic_year.manage'))<a class="link text-sm" href="{{ route('school-academic.assignments') }}">Manage teacher subject assignments in Academic Operations</a>@endif</div><div class="flex flex-wrap gap-2">@if($canManageTimetable && $canGenerate)<form method="POST" action="{{ route('timetable.generate') }}">@csrf<input type="hidden" name="academic_year_id" value="{{ $filters['academic_year_id'] }}"><input type="hidden" name="grade_id" value="{{ $filters['grade_id'] }}"><input type="hidden" name="section_id" value="{{ $filters['section_id'] }}"><button class="btn btn-primary">Generate {{ $generationSummary['missing_lessons'] }} missing {{ str('lesson')->plural($generationSummary['missing_lessons']) }}</button></form>@endif</div></div>
@if ($canManageTimetable)
    <div class="mb-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
        @if (! $hasSelectedClass)
            Select a grade and section to plan their timetable.
        @elseif ($periods->doesntContain('kind', 'lesson'))
            Add lesson periods in Period setup before generating the timetable.
        @elseif ($generationSummary['configured_assignments'] === 0)
            Period times are ready. Set required periods per week for this class's teacher subject assignments before generating lessons.
        @elseif ($generationSummary['missing_lessons'] === 0)
            All required weekly lessons are scheduled for this class. You can still edit or move them below.
        @elseif ($generationSummary['open_slots'] === 0)
            {{ $generationSummary['missing_lessons'] }} weekly {{ str('lesson')->plural($generationSummary['missing_lessons']) }} remain, but every lesson slot is occupied. Add a period or free a slot first.
        @else
            {{ $generationSummary['missing_lessons'] }} weekly {{ str('lesson')->plural($generationSummary['missing_lessons']) }} {{ $generationSummary['missing_lessons'] === 1 ? 'remains' : 'remain' }} across {{ $generationSummary['open_slots'] }} empty {{ str('slot')->plural($generationSummary['open_slots']) }}. Generating adds missing lessons only; existing lessons stay in place.
        @endif
    </div>
@endif

<section class="panel"><div class="panel-header"><form method="GET" class="grid w-full gap-3 md:grid-cols-4">
    <select name="academic_year_id"><option value="">Academic year</option>
        @foreach ($years as $year)
            <option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}</option>
        @endforeach
    </select>
    <select name="grade_id" data-grade-select data-section-target="#timetable-section"><option value="">Grade</option>
        @foreach ($grades as $grade)
            <option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? null) == $grade->id)>{{ $grade->name }}</option>
        @endforeach
    </select>
    <select id="timetable-section" name="section_id" disabled><option value="">Section</option>
        @foreach ($grades as $grade)
            @foreach ($grade->sections as $section)
                <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(($filters['section_id'] ?? null) == $section->id)>{{ $grade->name }} &middot; {{ $section->name }}</option>
            @endforeach
        @endforeach
    </select>
    <button class="btn btn-secondary">Load timetable</button>
</form></div></section>

@php
    $selectedGrade = $grades->firstWhere('id', $filters['grade_id'] ?? null);
    $selectedSection = $selectedGrade?->sections?->firstWhere('id', $filters['section_id'] ?? null);
@endphp
<div class="mt-6 grid gap-6 2xl:grid-cols-[300px_1fr]" data-timetable data-section-id="{{ $filters['section_id'] ?? '' }}" data-store-url="{{ route('timetable.entries.store') }}" data-entry-base-url="{{ url('/timetable/entries') }}">
    <aside class="space-y-6">
        <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Subjects to schedule</h2><p class="mt-1 text-xs text-slate-500">Drag a subject into this class schedule. Drag it again to create another weekly lesson.</p></div></div><div class="space-y-3 p-4">
            <div class="space-y-2">
            @forelse ($assignments as $assignment)
                <article class="schedule-card" draggable="{{ $canManageTimetable ? 'true' : 'false' }}" data-schedule-card data-assignment-id="{{ $assignment->id }}"><div class="font-medium">{{ $assignment->subject?->name }}</div><div class="text-xs text-slate-600">{{ $assignment->teacher?->full_name }}</div><div class="mt-1 text-xs text-slate-500">{{ $assignment->grade?->name }}{{ $assignment->section ? ' · '.$assignment->section->name : ' · All sections' }} · {{ $assignment->required_periods_per_week }} periods / week</div></article>
            @empty
                <div class="empty">Create teacher subject assignments for this class first.</div>
            @endforelse
            </div>
        </div></section>
        @if ($canManageTimetable)
        <section class="panel"><div class="panel-header"><h2 class="panel-title">Period setup</h2></div><form method="POST" action="{{ route('timetable.periods.store') }}" class="space-y-2 p-4">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $filters['academic_year_id'] }}"><div class="grid grid-cols-2 gap-2"><input type="number" name="period_number" min="1" required placeholder="#"><input name="label" required placeholder="Period 1"></div><div class="grid grid-cols-2 gap-2"><input type="time" name="starts_at"><input type="time" name="ends_at"></div><select name="kind"><option value="lesson">Lesson</option><option value="break">Break</option><option value="lunch">Lunch</option><option value="special">Special</option></select><button class="btn btn-secondary w-full">Save period</button>
        </form>
        @if ($periods->isNotEmpty())
            <div class="space-y-2 border-t border-slate-200 p-4">
                <p class="text-xs font-medium text-slate-500">Edit existing periods</p>
                @foreach ($periods as $period)
                    <details class="rounded-lg border border-slate-200 bg-white p-2 text-sm">
                        <summary class="cursor-pointer font-medium">{{ $period->label }} <span class="text-xs font-normal text-slate-500">{{ $period->starts_at ? substr($period->starts_at, 0, 5) : '' }}{{ $period->ends_at ? ' – '.substr($period->ends_at, 0, 5) : '' }}</span></summary>
                        <form method="POST" action="{{ route('timetable.periods.store') }}" class="mt-3 space-y-2">
                            @csrf
                            <input type="hidden" name="academic_year_id" value="{{ $period->academic_year_id }}">
                            <input type="hidden" name="period_number" value="{{ $period->period_number }}">
                            <label class="block text-xs">Label<input name="label" value="{{ $period->label }}" required class="mt-1 w-full"></label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="block text-xs">Start<input type="time" name="starts_at" value="{{ $period->starts_at ? substr($period->starts_at, 0, 5) : '' }}" class="mt-1 w-full"></label>
                                <label class="block text-xs">End<input type="time" name="ends_at" value="{{ $period->ends_at ? substr($period->ends_at, 0, 5) : '' }}" class="mt-1 w-full"></label>
                            </div>
                            <label class="block text-xs">Kind<select name="kind" class="mt-1 w-full"><option value="lesson" @selected($period->kind === 'lesson')>Lesson</option><option value="break" @selected($period->kind === 'break')>Break</option><option value="lunch" @selected($period->kind === 'lunch')>Lunch</option><option value="special" @selected($period->kind === 'special')>Special</option></select></label>
                            <button class="btn btn-secondary w-full">Save changes</button>
                        </form>
                        <form method="POST" action="{{ route('timetable.periods.destroy', $period) }}" class="mt-3 border-t border-slate-200 pt-3" onsubmit="return confirm('Delete this entire period row across all classes in this branch? This will also remove {{ (int) ($periodLessonCounts[$period->id] ?? 0) }} scheduled lessons. This cannot be undone.');">
                            @csrf @method('DELETE')
                            <p class="mb-2 text-xs text-slate-500">Removes this period from every class in this branch and its {{ (int) ($periodLessonCounts[$period->id] ?? 0) }} scheduled lessons.</p>
                            <button class="btn btn-secondary w-full text-rose-700">Delete entire period</button>
                        </form>
                    </details>
                @endforeach
            </div>
        @endif
        </section>
        @endif
    </aside>
    <section class="panel overflow-hidden"><div class="panel-header"><div><h2 class="panel-title">Class schedule</h2><p class="mt-1 text-xs text-slate-500">{{ $selectedGrade?->name ?? 'Select a grade' }}{{ $selectedSection ? ' · '.$selectedSection->name : '' }} · assigned subjects and teachers</p></div></div><div class="table-wrap"><table class="timetable-grid"><thead><tr><th>Period</th>
        @foreach ($days as $day)
            <th>{{ str($day)->headline() }}</th>
        @endforeach
    </tr></thead><tbody>
        @forelse ($periods as $period)
            <tr><th><div>{{ $period->label }}</div><div class="text-xs font-normal text-slate-500">{{ $period->starts_at ? substr($period->starts_at, 0, 5) : '' }}{{ $period->ends_at ? ' – '.substr($period->ends_at, 0, 5) : '' }}</div></th>
                @foreach ($days as $day)
                    @php($slotEntries = $entries->get($period->period_number.'-'.$day, collect()))
                    <td class="{{ $period->kind !== 'lesson' ? 'timetable-break' : '' }}">
                        <div class="schedule-dropzone" @if ($canManageTimetable && $period->kind === 'lesson') data-schedule-slot data-day="{{ $day }}" data-period="{{ $period->period_number }}" @endif>
                            @forelse ($slotEntries as $entry)
                                <article class="schedule-card schedule-card-filled" draggable="{{ $canManageTimetable ? 'true' : 'false' }}" data-schedule-card data-existing-entry-id="{{ $entry->public_id }}" data-assignment-id="{{ $entry->teacher_assignment_id }}" data-room="{{ $entry->room }}"><div class="font-medium">{{ $entry->subject?->name }}</div><div class="text-xs">{{ $entry->teacher?->full_name }}</div>
                                    @if ($entry->room)<div class="text-xs text-slate-500">Room {{ $entry->room }}</div>@endif
                                    @if ($canManageTimetable)
                                    <details class="mt-2 text-xs"><summary class="cursor-pointer text-blue-700">Edit lesson</summary>
                                        <form method="POST" action="{{ route('timetable.entries.update', $entry) }}" class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-white p-2">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="section_id" value="{{ $entry->section_id }}">
                                            <label class="block">Subject / teacher<select name="teacher_assignment_id" required class="mt-1 w-full">
                                                @if (! $assignments->contains('id', $entry->teacher_assignment_id))
                                                    <option value="{{ $entry->teacher_assignment_id }}" selected>{{ $entry->subject?->name }} · {{ $entry->teacher?->full_name }} (current)</option>
                                                @endif
                                                @foreach ($assignments as $assignment)
                                                    <option value="{{ $assignment->id }}" @selected($assignment->id === $entry->teacher_assignment_id)>{{ $assignment->subject?->name }} · {{ $assignment->teacher?->full_name }}</option>
                                                @endforeach
                                            </select></label>
                                            <label class="block">Day<select name="day_of_week" required class="mt-1 w-full">
                                                @foreach ($days as $editDay)
                                                    <option value="{{ $editDay }}" @selected(strtolower((string) $entry->day_of_week) === $editDay)>{{ str($editDay)->headline() }}</option>
                                                @endforeach
                                            </select></label>
                                            <label class="block">Period<select name="period_number" required class="mt-1 w-full">
                                                @foreach ($periods->where('kind', 'lesson') as $editPeriod)
                                                    <option value="{{ $editPeriod->period_number }}" @selected($editPeriod->period_number === $entry->period_number)>{{ $editPeriod->label }}</option>
                                                @endforeach
                                            </select></label>
                                            <label class="block">Room<input name="room" value="{{ $entry->room }}" maxlength="100" class="mt-1 w-full"></label>
                                            <button class="btn btn-secondary w-full">Save changes</button>
                                        </form>
                                    </details>
                                    <form method="POST" action="{{ route('timetable.entries.destroy', $entry) }}" class="mt-2">@csrf @method('DELETE')<button class="link text-xs text-rose-700">Remove</button></form>
                                    @endif
                                </article>
                            @empty
                                @if ($period->kind === 'lesson')
                                <span class="text-xs text-slate-400">Drop subject here</span>
                                @else
                                <span class="text-xs text-slate-500">{{ str($period->kind)->headline() }}</span>
                                @endif
                            @endforelse
                        </div>
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Add the working-day periods before building the timetable.</td></tr>
        @endforelse
    </tbody></table></div></section>
</div>
@endsection
