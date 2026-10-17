@extends('layouts.app', ['title' => 'Observations registry'])

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Observations registry</h1><p class="page-subtitle">Record and review student observations for the active academic year. Issues and formal actions remain in the separate issues registry.</p></div>
    <div class="flex flex-wrap gap-2"><a class="btn btn-secondary" href="{{ route('discipline.index') }}">Issues registry</a><a class="btn btn-secondary" href="{{ route('reports.school', ['report' => 'student_observations']) }}">Observation report</a></div>
</div>

@if ($canManage)
    <section class="panel mb-6">
        <div class="panel-header"><div><h2 class="panel-title">New observation</h2><p class="mt-1 text-xs text-slate-500">Teachers can record observations for their own assigned classes and subjects.</p></div></div>
        <form method="POST" action="{{ route('collaboration.performance.store') }}" class="space-y-4 p-5" data-submit-lock>
            @csrf
            <div class="form-grid">
                <div><label class="form-label">Student *</label><x-student-picker name="student_registration_id" value-key="registration" academic-year-id="{{ $filters['academic_year_id'] ?? '' }}" placeholder="Type student name or number" required /></div>
                <div><label class="form-label">Subject / class</label><select name="teacher_assignment_id"><option value="">General observation</option>@foreach($assignments as $assignment)<option value="{{ $assignment->id }}">{{ $assignment->subject?->name }} · {{ $assignment->grade?->name }}{{ $assignment->section ? ' '.$assignment->section->name : '' }}</option>@endforeach</select></div>
                <div><label class="form-label">Category *</label><select name="category" required>@foreach($categories as $category)<option value="{{ $category }}">{{ str($category)->headline() }}</option>@endforeach</select></div>
                <div><label class="form-label">Rating</label><select name="rating"><option value="">No numeric rating</option>@foreach(range(1, 5) as $rating)<option value="{{ $rating }}">{{ $rating }} / 5</option>@endforeach</select></div>
                <div><label class="form-label">Observed on *</label><input type="date" name="observed_on" value="{{ today()->toDateString() }}" required></div>
                <div><label class="form-label">Status *</label><select name="status"><option value="published">Published</option><option value="draft">Draft</option></select></div>
            </div>
            <div><label class="form-label">Observation *</label><textarea name="note" rows="3" required placeholder="Describe the observation, support need, or follow-up action."></textarea></div>
            <div class="flex justify-end"><button class="btn btn-primary">Save observation</button></div>
        </form>
    </section>
@endif

<section class="panel">
    <div class="panel-header"><form method="GET" class="grid w-full gap-2 md:grid-cols-3 xl:grid-cols-6">
        <select name="academic_year_id"><option value="">Active academic year</option>@foreach($years as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}</option>@endforeach</select>
        <select name="grade_id"><option value="">All classes</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? null) == $grade->id)>{{ $grade->name }}</option>@endforeach</select>
        <x-student-picker name="student_id" :academic-year-id="$filters['academic_year_id'] ?? null" :grade-id="$filters['grade_id'] ?? null" :section-id="$filters['section_id'] ?? null" :selected-id="$filters['student_id'] ?? null" :selected-label="$registrations->firstWhere('student_id', $filters['student_id'] ?? null)?->student?->full_name" placeholder="Filter student" />
        <select name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ str($category)->headline() }}</option>@endforeach</select>
        <select name="status"><option value="">All statuses</option><option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option></select>
        <button class="btn btn-secondary">Filter</button>
    </form></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Subject / teacher</th><th>Category</th><th>Observation</th><th>Status</th></tr></thead><tbody>
        @forelse($records as $record)
            <tr><td>{{ $record->observed_on?->format('d M Y') }}</td><td><a class="link" href="{{ route('students.show', [$record->student, 'tab' => 'observations']) }}">{{ $record->student?->full_name }}</a></td><td>{{ $record->registration?->grade?->name }}{{ $record->registration?->section ? ' '.$record->registration->section->name : '' }}</td><td>{{ $record->assignment?->subject?->name ?? 'General' }}<div class="text-xs text-slate-500">{{ $record->teacher?->full_name ?? 'School staff' }}</div></td><td>{{ str($record->category)->headline() }}{{ $record->rating ? ' · '.$record->rating.'/5' : '' }}</td><td class="max-w-lg whitespace-normal">{{ $record->note }}</td><td><span class="badge {{ $record->status === 'published' ? 'badge-green' : 'badge-amber' }}">{{ str($record->status)->headline() }}</span></td></tr>
        @empty
            <tr><td colspan="7" class="empty">No observations match the selected filters.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="border-t border-slate-200 p-3">{{ $records->links() }}</div>
</section>
@endsection
