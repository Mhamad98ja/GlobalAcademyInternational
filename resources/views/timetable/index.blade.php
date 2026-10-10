@extends('layouts.app', ['title' => 'Timetable'])

@section('content')
<div class="page-header"><div><h1 class="page-title">Graphical timetable</h1><p class="page-subtitle">Drag a teacher subject assignment into an empty class slot. Server-side checks prevent teacher, class, room, and workload conflicts.</p></div><a class="btn btn-secondary" href="{{ route('substitutions.index') }}">Substitutions</a></div>

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
        <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Subjects to schedule</h2><p class="mt-1 text-xs text-slate-500">Drag a subject into this class schedule.</p></div></div><div class="space-y-2 p-4">
            @forelse ($assignments as $assignment)
                <article class="schedule-card" draggable="true" data-schedule-card data-assignment-id="{{ $assignment->id }}"><div class="font-medium">{{ $assignment->subject?->name }}</div><div class="text-xs text-slate-600">{{ $assignment->teacher?->full_name }}</div><div class="mt-1 text-xs text-slate-500">{{ $assignment->grade?->name }}{{ $assignment->section ? ' · '.$assignment->section->name : ' · All sections' }} · {{ $assignment->required_periods_per_week }} periods / week</div></article>
            @empty
                <div class="empty">Create teacher subject assignments for this class first.</div>
            @endforelse
        </div></section>
        <section class="panel"><div class="panel-header"><h2 class="panel-title">Period setup</h2></div><form method="POST" action="{{ route('timetable.periods.store') }}" class="space-y-2 p-4">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $filters['academic_year_id'] }}"><div class="grid grid-cols-2 gap-2"><input type="number" name="period_number" min="1" required placeholder="#"><input name="label" required placeholder="Period 1"></div><div class="grid grid-cols-2 gap-2"><input type="time" name="starts_at"><input type="time" name="ends_at"></div><select name="kind"><option value="lesson">Lesson</option><option value="break">Break</option><option value="lunch">Lunch</option><option value="special">Special</option></select><button class="btn btn-secondary w-full">Save period</button>
        </form></section>
    </aside>
    <section class="panel overflow-hidden"><div class="panel-header"><div><h2 class="panel-title">Class schedule</h2><p class="mt-1 text-xs text-slate-500">{{ $selectedGrade?->name ?? 'Select a grade' }}{{ $selectedSection ? ' · '.$selectedSection->name : '' }} · assigned subjects and teachers</p></div></div><div class="table-wrap"><table class="timetable-grid"><thead><tr><th>Period</th>
        @foreach ($days as $day)
            <th>{{ str($day)->headline() }}</th>
        @endforeach
    </tr></thead><tbody>
        @forelse ($periods as $period)
            <tr><th><div>{{ $period->label }}</div><div class="text-xs font-normal text-slate-500">{{ $period->starts_at ? substr($period->starts_at, 0, 5) : '' }}{{ $period->ends_at ? ' – '.substr($period->ends_at, 0, 5) : '' }}</div></th>
                @foreach ($days as $day)
                    @php($entry = $entries->get($period->period_number.'-'.$day))
                    <td class="{{ $period->kind !== 'lesson' ? 'timetable-break' : '' }}">
                        <div class="schedule-dropzone" @if ($period->kind === 'lesson') data-schedule-slot data-day="{{ $day }}" data-period="{{ $period->period_number }}" @endif>
                            @if ($entry)
                                <article class="schedule-card schedule-card-filled" draggable="true" data-schedule-card data-existing-entry-id="{{ $entry->public_id }}" data-assignment-id="{{ $entry->teacher_assignment_id }}"><div class="font-medium">{{ $entry->subject?->name }}</div><div class="text-xs">{{ $entry->teacher?->full_name }}</div>
                                    @if ($entry->room)
                                        <div class="text-xs text-slate-500">Room {{ $entry->room }}</div>
                                    @endif
                                    <form method="POST" action="{{ route('timetable.entries.destroy', $entry) }}" class="mt-2">@csrf @method('DELETE')<button class="link text-xs text-rose-700">Remove</button></form>
                                </article>
                            @elseif ($period->kind === 'lesson')
                                <span class="text-xs text-slate-400">Drop subject here</span>
                            @else
                                <span class="text-xs text-slate-500">{{ str($period->kind)->headline() }}</span>
                            @endif
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
