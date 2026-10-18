@extends('layouts.app', ['title' => 'Add teacher subject assignment'])

@section('content')
<div class="page-header">
    <div>
        <a class="link text-sm" href="{{ route('school-academic.assignments', $selectedTeacher ? ['teacher_id' => $selectedTeacher->id] : []) }}">← Back to assignments</a>
        <h1 class="page-title mt-2">Add subject assignment</h1>
        <p class="page-subtitle">Create a subject assignment for one teacher and class.</p>
    </div>
</div>

<section class="panel max-w-3xl">
    <div class="panel-header"><div><h2 class="panel-title">Assignment details</h2><p class="mt-1 text-sm text-slate-500">The teacher is fixed from the selected teacher filter.</p></div></div>
    <form method="POST" action="{{ route('school-academic.assignments.store') }}" class="grid gap-4 p-5 md:grid-cols-2">
        @csrf
        <label class="form-label">Academic year<select name="academic_year_id" required>
            <option value="">Select academic year</option>
            @foreach ($years as $year)
                <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
            @endforeach
        </select></label>
        @if ($selectedTeacher)
            <div>
                <span class="form-label">Teacher</span>
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $selectedTeacher->full_name }}</div>
                <input type="hidden" name="teacher_id" value="{{ $selectedTeacher->id }}">
            </div>
        @else
            <label class="form-label">Teacher<select name="teacher_id" required>
                <option value="">Select teacher</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                @endforeach
            </select></label>
        @endif
        <label class="form-label">Subject<select name="subject_id" required>
            <option value="">Select subject</option>
            @foreach ($subjects as $subject)
                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
            @endforeach
        </select></label>
        <label class="form-label">Grade<select name="grade_id" required data-grade-select data-section-target="#assignment-create-section">
            <option value="">Select grade</option>
            @foreach ($grades as $grade)
                <option value="{{ $grade->id }}">{{ $grade->name }}</option>
            @endforeach
        </select></label>
        <label class="form-label">Section<select id="assignment-create-section" name="section_id" disabled>
            <option value="">All sections in grade</option>
            @foreach ($grades as $grade)
                @foreach ($grade->sections as $section)
                    <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}">{{ $grade->name }} &middot; {{ $section->name }}</option>
                @endforeach
            @endforeach
        </select></label>
        <label><span class="form-label">Teacher maximum periods per week</span><input type="number" min="1" max="60" name="weekly_period_limit" value="{{ old('weekly_period_limit') }}" placeholder="e.g. 25"></label>
        <textarea name="notes" rows="2" class="md:col-span-2" placeholder="Notes (optional)">{{ old('notes') }}</textarea>
        <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <div class="flex gap-2 md:col-span-2"><button class="btn btn-primary" type="submit">Create assignment</button><a class="btn btn-secondary" href="{{ route('school-academic.assignments', $selectedTeacher ? ['teacher_id' => $selectedTeacher->id] : []) }}">Cancel</a></div>
    </form>
</section>
@endsection
