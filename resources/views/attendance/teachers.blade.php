@extends('layouts.app', ['title' => 'Teacher attendance'])

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Teacher attendance IN / OUT</h1><p class="page-subtitle">Absences identify affected timetable lessons and generate fair substitute recommendations.</p></div>
    <a class="btn btn-secondary" href="{{ route('attendance.students') }}">Student attendance</a>
</div>

<section class="panel">
    <div class="panel-header"><form method="GET" class="flex w-full max-w-lg gap-2"><input type="date" name="attendance_date" value="{{ $date }}"><button class="btn btn-secondary">Load date</button></form></div>
    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($teachers as $teacher)
            @php($record = $records->get($teacher->id))
            <form method="POST" action="{{ route('attendance.teachers.save') }}" class="rounded-md border border-slate-200 p-4 space-y-3" data-submit-lock>
                @csrf
                <input type="hidden" name="teacher_id" value="{{ $teacher->id }}">
                <input type="hidden" name="attendance_date" value="{{ $date }}">
                <div><div class="font-medium">{{ $teacher->full_name }}</div><div class="text-xs text-slate-500">{{ $teacher->employee_number }}</div></div>
                <div class="grid grid-cols-2 gap-2"><label class="text-xs text-slate-600">Check in<input type="time" name="checked_in_at" value="{{ $record?->checked_in_at }}"></label><label class="text-xs text-slate-600">Check out<input type="time" name="checked_out_at" value="{{ $record?->checked_out_at }}"></label></div>
                <label class="text-xs text-slate-600">Status<select name="status">
                    @foreach (['present' => 'Present', 'late' => 'Late', 'early_leave' => 'Early leave', 'excused_absence' => 'Excused absence', 'unexcused_absence' => 'Unexcused absence'] as $value => $label)
                        <option value="{{ $value }}" @selected(($record?->status ?? 'present') === $value)>{{ $label }}</option>
                    @endforeach
                </select></label>
                <div class="grid grid-cols-2 gap-2"><label class="text-xs text-slate-600">Late minutes<input type="number" min="0" name="late_minutes" value="{{ $record?->late_minutes ?? 0 }}"></label><label class="text-xs text-slate-600">Early leave<input type="number" min="0" name="early_leave_minutes" value="{{ $record?->early_leave_minutes ?? 0 }}"></label></div>
                <input name="notes" value="{{ $record?->notes }}" placeholder="Optional note">
                <button class="btn btn-secondary btn-small w-full">Save attendance</button>
            </form>
        @empty
            <div class="empty md:col-span-2 xl:col-span-3">No active teachers found.</div>
        @endforelse
    </div>
</section>
@endsection
