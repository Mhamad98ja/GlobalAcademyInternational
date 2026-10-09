@extends('layouts.app', ['title' => 'Student attendance'])

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Student attendance</h1><p class="page-subtitle">Record daily or period attendance for an active class roster.</p></div>
    @if(auth()->user()->hasPermission('reports.view'))<a class="btn btn-secondary" href="{{ route('reports.school', ['report' => 'student_attendance', 'module' => 'students'] + $filters) }}">Attendance report</a>@endif
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="grid w-full gap-3 md:grid-cols-2 xl:grid-cols-5">
            <select name="academic_year_id" aria-label="Academic year">
                <option value="">Academic year</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}</option>
                @endforeach
            </select>
            <select name="grade_id" aria-label="Grade" data-grade-select data-section-target="#attendance-section">
                <option value="">All grades</option>
                @foreach ($grades as $grade)
                    <option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? null) == $grade->id)>{{ $grade->name }}</option>
                @endforeach
            </select>
            <select id="attendance-section" name="section_id" aria-label="Section" disabled>
                <option value="">All sections</option>
                @foreach ($grades as $grade)
                    @foreach ($grade->sections as $section)
                        <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(($filters['section_id'] ?? null) == $section->id)>{{ $grade->name }} &middot; {{ $section->name }}</option>
                    @endforeach
                @endforeach
            </select>
            <input type="date" name="attendance_date" aria-label="Attendance date" value="{{ $filters['attendance_date'] }}">
            <div><label class="form-label" for="attendance-period">Period (0 = daily)</label><input id="attendance-period" type="number" name="period_number" min="0" max="30" value="{{ $filters['period_number'] }}"></div>
            <select name="teacher_assignment_id" aria-label="Course assignment"><option value="">All courses</option>@foreach($assignments as $assignment)<option value="{{ $assignment->id }}" @selected(($filters['teacher_assignment_id'] ?? null) == $assignment->id)>{{ $assignment->subject?->name }} &middot; {{ $assignment->teacher?->full_name }} &middot; {{ $assignment->grade?->name }} {{ $assignment->section?->name }}</option>@endforeach</select>
            <select name="status" aria-label="Attendance status"><option value="">All statuses</option>@foreach(['present', 'absent', 'late', 'excused', 'left_early'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>@endforeach</select>
            @if(!empty($filters['student_id']))<input type="hidden" name="student_id" value="{{ $filters['student_id'] }}">@endif
            <button class="btn btn-secondary">Load roster</button>
            <a class="btn btn-secondary" href="{{ route('attendance.students') }}">Reset</a>
        </form>
    </div>
    <form method="POST" action="{{ route('attendance.students.save') }}" data-submit-lock>
        @csrf
        <input type="hidden" name="academic_year_id" value="{{ $filters['academic_year_id'] }}">
        <input type="hidden" name="attendance_date" value="{{ $filters['attendance_date'] }}">
        @foreach(['period_number', 'teacher_assignment_id', 'grade_id', 'section_id', 'student_id'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $filters[$field] ?? '' }}">@endforeach
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5">
            <p class="text-sm text-slate-600">{{ $registrations->count() }} students &middot; {{ $filters['attendance_date'] }} &middot; {{ $filters['period_number'] ? 'Period '.$filters['period_number'] : 'Daily attendance' }}</p>
            <button type="submit" class="btn btn-primary" @disabled($registrations->isEmpty())>Save attendance</button>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Student</th><th>Class</th><th>Status</th><th>Note</th></tr></thead>
                <tbody>
                    @forelse ($registrations as $registration)
                        @php($record = $existing->get($registration->id))
                        <tr>
                            <td><input type="hidden" name="entries[{{ $loop->index }}][registration_id]" value="{{ $registration->id }}"><a class="link font-medium" href="{{ route('students.show', $registration->student) }}">{{ $registration->student?->full_name }}</a><div class="text-xs text-slate-500">{{ $registration->student?->student_number }}</div></td>
                            <td>{{ $registration->grade?->name }} {{ $registration->section?->name }}</td>
                            <td>
                                <select name="entries[{{ $loop->index }}][status]">
                                    @foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused', 'left_early' => 'Left early'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($record?->status ?? 'present') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="entries[{{ $loop->index }}][notes]" value="{{ $record?->notes }}" placeholder="Optional note"></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Choose an academic year and class to load active students.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</section>
@endsection
