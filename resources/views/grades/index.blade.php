@extends('layouts.app', ['title' => $view === 'grade-book' ? 'Grade Book' : 'Evaluation Grades'])
@php
    $assessmentTypes = ['test' => 'Test', 'quiz' => 'Quiz', 'exam' => 'Exam', 'monthly_test' => 'Monthly test', 'project' => 'Project', 'oral' => 'Oral assessment'];
@endphp
@section('content')
<section class="dars-evaluation-page">
    <div class="dars-evaluation-actions">
        <form method="GET" action="{{ route('grades.index') }}" class="dars-evaluation-assignment-picker">
            <input type="hidden" name="view" value="{{ $view }}">
            <select name="teacher_assignment_id" onchange="this.form.submit()" aria-label="Filter class subject">
                <option value="">All assigned classes and subjects</option>
                @foreach($assignments as $item)
                    <option value="{{ $item->id }}" @selected($assignment?->id === $item->id)>{{ $item->grade?->name }}{{ $item->section ? ' / '.$item->section->name : '' }} · {{ $item->subject?->name }}</option>
                @endforeach
            </select>
        </form>
        <nav class="dars-evaluation-action-links" aria-label="Evaluation tools">
            <a class="{{ $view === 'evaluations' ? 'is-active' : '' }}" href="{{ route('grades.index', ['view' => 'evaluations']) }}">Evaluation</a>
            <a href="{{ route('collaboration.section', ['section' => 'evaluation-dashboard']) }}">Dashboard</a>
            @if($activeTerm && $assignments->isNotEmpty())<a class="dars-evaluation-add" href="{{ route('grades.assessments.create', ['teacher_assignment_id' => $assignment?->id ?: $assignments->first()->id]) }}">+ New evaluation</a>@elseif($assignments->isEmpty())<span class="dars-evaluation-context">No assigned classes or subjects</span>@else<span class="dars-evaluation-context">Configure an active academic term</span>@endif
        </nav>
    </div>
    @if($view === 'evaluations')
        <section class="dars-evaluation-list-panel">
            <div class="dars-evaluation-list-toolbar"><div><strong>Evaluation grades</strong><small>Current active term: {{ $activeTerm?->name ?? 'Not configured' }}. Teachers see their own evaluations; administration sees every teacher evaluation.</small></div><span class="dars-evaluation-context">{{ $evaluationIndexAssessments->count() }} evaluation{{ $evaluationIndexAssessments->count() === 1 ? '' : 's' }}</span></div>
            @if($evaluationIndexAssessments->isNotEmpty())
                <div class="table-wrap"><table class="dars-evaluation-table"><thead><tr><th>Evaluation</th><th>Teacher / class / subject</th><th>Term</th><th>Grade over</th><th>Evaluation date</th><th>Status</th><th></th></tr></thead><tbody>
                @foreach($evaluationIndexAssessments as $item)
                    @php($reviewStatus = $item->review_status ?? ($item->status === 'open' ? 'approved' : $item->status))
                    @php($reviewLabel = $reviewStatus === 'draft' ? 'Saved' : str($reviewStatus)->headline())
                    <tr><td><strong>{{ $item->name }}</strong><small>{{ $assessmentTypes[$item->type] ?? str($item->type)->headline() }}@if($item->notes)<br>{{ $item->notes }}@endif</small></td><td><strong>{{ $item->assignment?->teacher?->full_name ?: 'Unassigned teacher' }}</strong><small>{{ $item->assignment?->grade?->name }}{{ $item->assignment?->section ? ' / '.$item->assignment->section->name : '' }} · {{ $item->assignment?->subject?->name }}</small></td><td>{{ $item->term?->name ?: 'No term assigned' }}</td><td>{{ rtrim(rtrim(number_format((float) $item->maximum_score, 2), '0'), '.') }}<small>Weight {{ rtrim(rtrim(number_format((float) $item->weight, 3), '0'), '.') }}</small></td><td>{{ $item->assessment_date?->format('d/m/Y') ?: '—' }}</td><td><span class="dars-evaluation-status {{ $reviewStatus }}">{{ $reviewLabel }}</span></td><td class="flex gap-2">@if(in_array($reviewStatus, ['draft', 'rejected'], true))<a href="{{ route('grades.assessments.edit', $item) }}">Edit</a>@endif<a href="{{ route('grades.index', ['view' => 'grade-book', 'teacher_assignment_id' => $item->teacher_assignment_id, 'assessment_id' => $item->id]) }}">Open grades</a></td></tr>
                @endforeach
                </tbody></table></div>
            @else
                <div class="dars-evaluation-empty"><strong>No evaluations found</strong><span>Use New evaluation to create the first class evaluation.</span></div>
            @endif
        </section>
    @else
        <section class="dars-grade-book-panel">
            <form method="GET" action="{{ route('grades.index') }}" class="dars-grade-book-filters"><input type="hidden" name="view" value="grade-book"><label>Class subject<select name="teacher_assignment_id" onchange="this.form.submit()"><option value="">Choose class subject</option>@foreach($assignments as $item)<option value="{{ $item->id }}" @selected($assignment?->id === $item->id)>{{ $item->grade?->name }}{{ $item->section ? ' / '.$item->section->name : '' }} · {{ $item->subject?->name }}</option>@endforeach</select></label><label>Evaluation<select name="assessment_id" onchange="this.form.submit()"><option value="">Choose evaluation</option>@foreach($assessments as $item)<option value="{{ $item->id }}" @selected($assessment?->id === $item->id)>{{ $item->name }} · {{ $item->assessment_date?->format('d M Y') }}</option>@endforeach</select></label></form>
            @if($assignment && $assessment)
                @php($assessmentReviewStatus = $assessment->review_status ?? ($assessment->status === 'open' ? 'approved' : $assessment->status))
                @php($canEditGrades = auth()->user()->hasPermission('academic_year.manage') || $assessment->status === 'open' || in_array($assessmentReviewStatus, ['draft', 'rejected'], true) || $hasApprovedModificationRequest)
                @if(!$canEditGrades)<div class="dars-evaluation-notice">This evaluation is <strong>{{ $assessmentReviewStatus === 'submitted' ? 'Submitted' : str($assessmentReviewStatus)->headline() }}</strong> and is waiting for administration review.</div>@endif
                <form method="POST" action="{{ route('grades.save') }}" data-submit-lock>@csrf<input type="hidden" name="teacher_assignment_id" value="{{ $assignment->id }}"><input type="hidden" name="assessment_id" value="{{ $assessment->id }}"><input type="hidden" name="save_as" value="draft"><header class="dars-grade-book-header"><div><strong>{{ $assessment->name }}</strong><span>{{ $assignment->subject?->name }} · {{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }} · {{ $assessment->term?->name ?: 'No term' }} · Grade over {{ rtrim(rtrim(number_format((float) $assessment->maximum_score, 2), '0'), '.') }} · Weight {{ rtrim(rtrim(number_format((float) $assessment->weight, 3), '0'), '.') }}</span></div><div>@if($canEditGrades)<button class="dars-evaluation-secondary" type="submit">Save draft</button>@if(in_array($assessmentReviewStatus, ['draft', 'rejected'], true))<button class="dars-evaluation-primary" type="submit" name="submit_for_review" value="1">Submit to administration</button>@elseif(auth()->user()->hasPermission('grades.publish'))<button class="dars-evaluation-primary" name="save_as" value="published" type="submit">Publish grades</button>@endif @endif</div></header><table class="dars-grade-book-table"><thead><tr><th>Student</th><th>Grade / {{ rtrim(rtrim(number_format((float) $assessment->maximum_score, 2), '0'), '.') }}</th><th>Remarks</th><th>Status</th></tr></thead><tbody>@forelse($registrations as $registration)@php($entry = $grades->get($registration->id))<tr><td><input type="hidden" name="entries[{{ $loop->index }}][registration_id]" value="{{ $registration->id }}"><strong>{{ $registration->student?->full_name }}</strong><small>{{ $registration->student?->student_number }}</small></td><td><input type="number" min="0.1" max="{{ $assessment->maximum_score }}" step="0.1" name="entries[{{ $loop->index }}][score]" value="{{ $entry?->score }}" @disabled($entry?->is_locked || !$canEditGrades)></td><td><input name="entries[{{ $loop->index }}][notes]" value="{{ $entry?->notes }}" placeholder="Remarks" @disabled($entry?->is_locked || !$canEditGrades)></td><td>@if($entry)<span class="dars-evaluation-status {{ $entry->status }}">{{ $entry->is_locked ? 'Locked' : str($entry->status)->headline() }}</span>@else<span class="dars-grade-new">New</span>@endif</td></tr>@empty<tr><td colspan="4" class="dars-evaluation-empty-row">No active students match this class subject.</td></tr>@endforelse</tbody></table></form>
            @else
                <div class="dars-evaluation-empty"><strong>Choose an evaluation</strong><span>Select a class subject and evaluation to open its student grade entry.</span></div>
            @endif
        </section>
    @endif
</section>
@endsection
