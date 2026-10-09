@php($agendaPage = request('section'))
<section class="dars-agenda-page">
    @if($agendaPage === 'homework')
        <div class="dars-agenda-toolbar">
            <button class="approve">✓&nbsp; Approve</button><button class="reject">×&nbsp; Reject</button><button>＋&nbsp; Add</button><button aria-label="Print">▣</button>
        </div>
        <form method="GET" class="dars-agenda-controls">
            <input type="hidden" name="section" value="homework">
            <label>Choose class<select name="grade_id"><option value=""></option>@foreach($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->name }}</option>@endforeach</select></label>
            <label class="dars-agenda-radio"><input type="radio" name="sort" checked> Publish date</label>
            <label class="dars-agenda-radio"><input type="radio" name="sort"> Due date</label>
            <label class="dars-agenda-period"><strong>Agenda Of {{ today()->startOfWeek()->format('d/m/Y') }} - {{ today()->endOfWeek()->format('d/m/Y') }}</strong><input type="date" name="date" value="{{ today()->toDateString() }}"></label>
        </form>
        <table class="dars-agenda-grid"><thead><tr><th></th>@foreach(collect(range(0,4))->map(fn($offset) => today()->startOfWeek()->addDays($offset)) as $day)<th>{{ $day->format('l d F') }}</th>@endforeach</tr></thead><tbody><tr><td colspan="6"><span class="dars-agenda-refused">* Refused</span><span class="dars-agenda-added">* Added</span><span class="dars-agenda-modified">* Modified</span><span class="dars-agenda-approved">* Accepted</span></td></tr>@forelse($contents as $content)<tr><td>{{ $content->due_on?->format('d/m') ?: '—' }}</td><td colspan="5"><a class="link" href="#course-content-{{ $content->id }}">{{ $content->title }}</a> · {{ $content->assignment?->subject?->name }} · {{ $content->assignment?->grade?->name }}</td></tr>@empty<tr><td colspan="6">No course work matches the selected date and class.</td></tr>@endforelse</tbody></table>
    @elseif($agendaPage === 'homework-report')
        <a class="dars-agenda-back" href="{{ route('collaboration.index', ['section' => 'homework']) }}">←</a>
        <form method="GET" class="dars-agenda-report-filters">
            <input type="hidden" name="section" value="homework-report">
            <label>Class:<select><option></option>@foreach($grades as $grade)<option>{{ $grade->name }}</option>@endforeach</select></label>
            <label>Teacher:<select><option></option>@foreach($assignments as $assignment)<option>{{ $assignment->teacher?->full_name }}</option>@endforeach</select></label>
            <label>Start Date:<input type="date" value="{{ today()->toDateString() }}"></label><label>End Date:<input type="date" value="{{ today()->toDateString() }}"></label>
            <label>Format:<select><option>Detailed agenda</option><option>Summary agenda</option></select></label><button class="dars-agenda-search" aria-label="Run report">⌕</button>
        </form>
        <div class="dars-agenda-viewer"><header><strong>{{ $contents->count() }} course items</strong><span>Filtered agenda</span><a class="dars-agenda-search" href="{{ route('collaboration.section', ['section' => 'homework']) }}">Reset</a></header><div>@forelse($contents as $content)<p><strong>{{ $content->title }}</strong><br>{{ $content->due_on?->format('d M Y') ?: 'No due date' }} · {{ $content->assignment?->teacher?->full_name }}</p>@empty<p>No homework is recorded for this filter.</p>@endforelse</div></div>
    @else
        <a class="dars-agenda-back" href="{{ route('collaboration.index', ['section' => 'homework']) }}">←</a>
        <form method="GET" class="dars-agenda-comparison-filters"><input type="hidden" name="section" value="{{ $agendaPage }}"><label>Date:<input type="date" value="{{ today()->toDateString() }}"></label><label>Classe:<select><option>All classes</option>@foreach($grades as $grade)<option>{{ $grade->name }}</option>@endforeach</select></label><button class="dars-agenda-search" aria-label="Search">⌕</button></form>
        <div class="dars-agenda-classes">@forelse($grades as $grade)<label><input type="checkbox"> {{ $grade->name }}</label>@empty<div>No classes available.</div>@endforelse</div>
    @endif
</section>
