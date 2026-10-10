@extends('layouts.app', ['title' => request('view') === 'grade-book' ? 'Grade Book' : 'Evaluation Grades'])

@php
    $view = in_array(request('view'), ['evaluations', 'grade-book'], true) ? request('view') : 'evaluations';
    $assessmentTypes = ['test' => 'Test', 'quiz' => 'Quiz', 'exam' => 'Exam', 'monthly_test' => 'Monthly test', 'project' => 'Project', 'oral' => 'Oral assessment'];
@endphp
@section('content')
<section class="dars-evaluation-page">
    <div class="dars-evaluation-actions">
        <form method="GET" action="{{ route('grades.index') }}" class="dars-evaluation-assignment-picker">
            <input type="hidden" name="view" value="{{ $view }}">
            <select name="teacher_assignment_id" onchange="this.form.submit()" aria-label="Choose class subject">
                <option value="">Choose class subject</option>
                @foreach($assignments as $item)
                    <option value="{{ $item->id }}" @selected($assignment?->id === $item->id)>{{ $item->grade?->name }}{{ $item->section ? ' / '.$item->section->name : '' }} · {{ $item->subject?->name }}</option>
                @endforeach
            </select>
        </form>
        <nav class="dars-evaluation-action-links" aria-label="Evaluation tools">
            <a class="{{ $view === 'evaluations' ? 'is-active' : '' }}" href="{{ route('grades.index', ['view' => 'evaluations', 'teacher_assignment_id' => $assignment?->id]) }}"><span>☷</span> Evaluation</a>
            <a class="{{ $view === 'grade-book' ? 'is-active' : '' }}" href="{{ route('grades.index', ['view' => 'grade-book', 'teacher_assignment_id' => $assignment?->id, 'assessment_id' => $assessment?->id]) }}"><span>▣</span> Grade Book</a>
            <a href="{{ route('collaboration.section', ['section' => 'evaluation-dashboard']) }}"><span>▦</span> Dashboard</a>
            <a class="dars-evaluation-add" href="{{ route('grades.index', ['view' => 'evaluations', 'teacher_assignment_id' => $assignment?->id, 'add' => 1]) }}#assessment-create"><span>+</span> Add new</a>
        </nav>
    </div>

    @if($view === 'evaluations')
        <section class="dars-evaluation-list-panel">
            <div class="dars-evaluation-list-toolbar">
                <form method="GET" action="{{ route('grades.index') }}" class="dars-evaluation-class-filter">
                    <input type="hidden" name="view" value="evaluations">
                    <label>Choose Class<select name="teacher_assignment_id" onchange="this.form.submit()"><option value="">Choose Class</option>@foreach($assignments as $item)<option value="{{ $item->id }}" @selected($assignment?->id === $item->id)>{{ $item->grade?->name }}{{ $item->section ? ' / '.$item->section->name : '' }} · {{ $item->subject?->name }}</option>@endforeach</select></label>
                </form>
                @if($assignment)<span class="dars-evaluation-context">{{ $assignment->subject?->name }} · {{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }}</span>@endif
            </div>
            @if($assignment && $assessments->isNotEmpty())
                <table class="dars-evaluation-table">
                    <thead><tr><th>Evaluation</th><th>Column</th><th>Type</th><th>Grade over</th><th>Weight</th><th>Evaluation date</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($assessments as $item)
                        <tr><td><strong>{{ $item->name }}</strong>@if($item->notes)<small>{{ $item->notes }}</small>@endif</td><td>{{ $item->assessment_column ?: '—' }}</td><td>{{ $assessmentTypes[$item->type] ?? str($item->type)->headline() }}</td><td>{{ rtrim(rtrim(number_format((float) $item->maximum_score, 2), '0'), '.') }}</td><td>{{ rtrim(rtrim(number_format((float) $item->weight, 3), '0'), '.') }}</td><td>{{ $item->assessment_date?->format('d/m/Y') ?: '—' }}</td><td><span class="dars-evaluation-status {{ $item->status }}">{{ str($item->status)->headline() }}</span></td><td><a href="{{ route('grades.index', ['view' => 'grade-book', 'teacher_assignment_id' => $assignment->id, 'assessment_id' => $item->id]) }}">Open</a></td></tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="dars-evaluation-empty"><strong>⌁</strong><span>No evaluations found.</span></div>
            @endif
        </section>

        @if($assignment)
            @include('grades.assessment-create')
        @endif
    @else
        <section class="dars-grade-book-panel">
            <form method="GET" action="{{ route('grades.index') }}" class="dars-grade-book-filters">
                <input type="hidden" name="view" value="grade-book">
                <label>Class subject<select name="teacher_assignment_id" onchange="this.form.submit()"><option value="">Choose class subject</option>@foreach($assignments as $item)<option value="{{ $item->id }}" @selected($assignment?->id === $item->id)>{{ $item->grade?->name }}{{ $item->section ? ' / '.$item->section->name : '' }} · {{ $item->subject?->name }}</option>@endforeach</select></label>
                <label>Evaluation<select name="assessment_id" onchange="this.form.submit()"><option value="">Choose evaluation</option>@foreach($assessments as $item)<option value="{{ $item->id }}" @selected($assessment?->id === $item->id)>{{ $item->name }} · {{ $item->assessment_date?->format('d M Y') }}</option>@endforeach</select></label>
            </form>
            @if($assignment && $assessment)
                <form method="POST" action="{{ route('grades.save') }}" data-submit-lock>
                    @csrf
                    <input type="hidden" name="teacher_assignment_id" value="{{ $assignment->id }}"><input type="hidden" name="assessment_id" value="{{ $assessment->id }}">
                    <header class="dars-grade-book-header"><div><strong>{{ $assessment->name }}</strong><span>{{ $assignment->subject?->name }} · {{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }} · Grade over {{ rtrim(rtrim(number_format((float) $assessment->maximum_score, 2), '0'), '.') }} · Weight {{ rtrim(rtrim(number_format((float) $assessment->weight, 3), '0'), '.') }}</span></div><div><button class="dars-evaluation-secondary" name="save_as" value="draft">Save</button><button class="dars-evaluation-primary" name="save_as" value="published">Submit</button></div></header>
                    <table class="dars-grade-book-table"><thead><tr><th>Student</th><th>Grade / {{ rtrim(rtrim(number_format((float) $assessment->maximum_score, 2), '0'), '.') }}</th><th>Remarks</th><th>Status</th></tr></thead><tbody>
                    @forelse($registrations as $registration)
                        @php($entry = $grades->get($registration->id))
                        <tr><td><input type="hidden" name="entries[{{ $loop->index }}][registration_id]" value="{{ $registration->id }}"><strong>{{ $registration->student?->full_name }}</strong><small>{{ $registration->student?->student_number }}</small></td><td><input type="number" min="0" max="{{ $assessment->maximum_score }}" step="0.01" name="entries[{{ $loop->index }}][score]" value="{{ $entry?->score }}" @disabled($entry?->is_locked)></td><td><input name="entries[{{ $loop->index }}][notes]" value="{{ $entry?->notes }}" placeholder="Remarks" @disabled($entry?->is_locked)></td><td>@if($entry)<span class="dars-evaluation-status {{ $entry->status }}">{{ $entry->is_locked ? 'Locked' : str($entry->status)->headline() }}</span>@else<span class="dars-grade-new">New</span>@endif</td></tr>
                    @empty<tr><td colspan="4" class="dars-evaluation-empty-row">No active students match this class subject.</td></tr>@endforelse
                    </tbody></table>
                </form>
            @else
                <div class="dars-evaluation-empty"><strong>▤</strong><span>Choose a class subject and evaluation to open the grade book.</span></div>
            @endif
        </section>
    @endif
</section>
@endsection
