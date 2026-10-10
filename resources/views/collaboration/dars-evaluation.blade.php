@php($evaluationPage = $evaluationPage ?? request('section'))
<section class="dars-evaluation-page">
    <div class="dars-evaluation-actions">
        <form method="GET" action="{{ route('collaboration.section', ['section' => 'evaluation-dashboard']) }}" class="dars-evaluation-assignment-picker"><select aria-label="Choose class subject"><option>Choose class subject</option>@foreach($assignments as $assignment)<option>{{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }} · {{ $assignment->subject?->name }}</option>@endforeach</select></form>
        <nav class="dars-evaluation-action-links" aria-label="Evaluation tools"><a class="is-active" href="{{ route('collaboration.section', ['section' => 'evaluation-dashboard']) }}"><span>☷</span> Evaluation</a><a href="{{ route('grades.index', ['view' => 'grade-book']) }}"><span>▣</span> Grade Book</a><a class="is-muted" href="{{ route('collaboration.section', ['section' => 'evaluation-dashboard']) }}"><span>▦</span> Dashboard</a><a class="dars-evaluation-add" href="{{ route('grades.index', ['view' => 'evaluations', 'add' => 1]) }}#assessment-create"><span>+</span> Add new</a></nav>
    </div>
    @if($evaluationPage === 'modification-requests')
        <section class="dars-evaluation-requests">
            <form><label>Class<select><option></option>@foreach($assignments as $assignment)<option>{{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }}</option>@endforeach</select></label><label>Column<select><option></option><option>1 - Cntr1</option><option>2 - Cntr2</option></select></label><label>Status<select><option></option><option>Submitted</option><option>Approved</option><option>Rejected</option></select></label></form>
            <div class="dars-evaluation-request-empty"><strong>No request found</strong></div>
        </section>
    @else
        <section class="dars-evaluation-dashboard-empty"></section>
    @endif
</section>
