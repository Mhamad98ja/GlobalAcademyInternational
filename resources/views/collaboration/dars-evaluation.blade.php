@php($evaluationPage = $evaluationPage ?? request('section'))
<section class="dars-evaluation-page">
    <div class="dars-evaluation-actions">
        <form method="GET" action="{{ route('collaboration.section', ['section' => 'evaluation-dashboard']) }}" class="dars-evaluation-assignment-picker">
            <input type="hidden" name="section" value="evaluation-dashboard">
            <select name="teacher_assignment_id" aria-label="Choose class subject" onchange="this.form.submit()">
                <option value="">All assigned subjects</option>
                @foreach($assignments as $assignment)
                    <option value="{{ $assignment->id }}" @selected($evaluationAssignmentId === $assignment->id)>{{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }} · {{ $assignment->subject?->name }}</option>
                @endforeach
            </select>
        </form>
        <nav class="dars-evaluation-action-links" aria-label="Evaluation tools">
            <a href="{{ route('grades.index', ['view' => 'evaluations']) }}">Evaluation</a>
            <a class="{{ $evaluationPage === 'evaluation-dashboard' ? 'is-active' : '' }}" href="{{ route('collaboration.section', ['section' => 'evaluation-dashboard']) }}">Dashboard</a>
            @if($activeAcademicTerm)<a class="dars-evaluation-add" href="{{ route('grades.assessments.create') }}">+ New evaluation</a>@else<span class="dars-evaluation-context">Configure an active academic term</span>@endif
            <a class="{{ $evaluationPage === 'modification-requests' ? 'is-active' : '' }}" href="{{ route('collaboration.section', ['section' => 'modification-requests']) }}">Modification request</a>
        </nav>
    </div>
    @if($evaluationPage === 'modification-requests')
        @include('collaboration.dars-evaluation-modification-requests')
    @else
        <section class="dars-evaluation-dashboard">
            <header class="dars-evaluation-dashboard-header">
                <div><strong>Evaluation overview</strong><span>Each square represents an evaluation, grouped by academic term and assessment type.</span></div>
                <span>{{ $evaluationAssignments->count() }} assigned subject{{ $evaluationAssignments->count() === 1 ? '' : 's' }}</span>
            </header>
            <div class="dars-evaluation-dashboard-scroll">
                <table class="dars-evaluation-grid" aria-label="Subject evaluation dashboard">
                    <thead>
                        <tr>
                            <th rowspan="2" scope="col">Subject / class</th>
                            @foreach($evaluationColumns as $column)
                                <th colspan="{{ $evaluationTypes->count() }}" scope="colgroup">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($evaluationColumns as $column)
                                @foreach($evaluationTypes as $type)
                                    <th scope="col">{{ $type['label'] }}</th>
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($evaluationAssignments as $assignment)
                            <tr>
                                <th scope="row" class="dars-evaluation-subject"><strong>{{ $assignment->subject?->name ?: 'Unassigned subject' }}</strong><small>{{ $assignment->grade?->name }}{{ $assignment->section ? ' / '.$assignment->section->name : '' }} · {{ $assignment->teacher?->full_name ?: 'No teacher' }}</small></th>
                                @foreach($evaluationColumns as $column)
                                    @foreach($evaluationTypes as $type)
                                        @php($cellItems = $evaluationDashboardAssessments->filter(fn (array $item): bool => $item['teacher_assignment_id'] === $assignment->id && $item['column_key'] === $column['key'] && $item['type_key'] === $type['key']))
                                        <td class="dars-evaluation-cell">
                                            @forelse($cellItems as $item)
                                                <a class="dars-evaluation-tile dars-evaluation-tile-{{ $item['type_key'] }}" data-evaluation-tile href="{{ route('grades.index', ['view' => 'grade-book', 'teacher_assignment_id' => $assignment->id, 'assessment_id' => $item['id']]) }}" title="{{ $item['name'] }} · {{ $item['term_label'] }} · {{ $item['status_label'] }}{{ $item['assessment_date'] ? ' · '.$item['assessment_date'] : '' }}" aria-label="{{ $item['name'] }} · {{ $item['term_label'] }}">
                                                    <span aria-hidden="true"></span><span class="sr-only">{{ $item['name'] }} · {{ $item['term_label'] }}</span>
                                                </a>
                                            @empty
                                                <span class="dars-evaluation-cell-empty" aria-hidden="true">—</span>
                                            @endforelse
                                        </td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ 1 + ($evaluationColumns->count() * $evaluationTypes->count()) }}" class="dars-evaluation-grid-empty">No subject assignments are available for this dashboard.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="dars-evaluation-dashboard-legend"><span><i class="dars-evaluation-tile dars-evaluation-tile-project"><b></b></i> Project</span><span><i class="dars-evaluation-tile dars-evaluation-tile-quiz"><b></b></i> Quiz</span><span>Open a square to view its grade book.</span></div>
        </section>
    @endif
</section>
