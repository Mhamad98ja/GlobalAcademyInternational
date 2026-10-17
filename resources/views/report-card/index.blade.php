@extends('layouts.app', ['title' => 'Annual & Partial Report Cards'])

@section('content')
<section class="admin-report-card-page">
    <div class="page-header">
        <div><div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[.1em] text-slate-500"><a class="link" href="{{ route('workspaces.show', ['workspace' => 'operations']) }}">Administration &amp; Operations</a><span aria-hidden="true">/</span><span>Report cards</span></div><h1 class="page-title">Annual &amp; Partial Report Cards</h1><p class="page-subtitle">Generate official report cards from earned grades that are published or locked, for one student or an entire class.</p></div>
        <div class="flex flex-wrap gap-2"><a class="btn btn-secondary" href="{{ route('workspaces.show', ['workspace' => 'operations']) }}">Back to administration</a>@if($generated && $cards->isNotEmpty())<a class="btn btn-primary" href="{{ route('admin.report-cards.print', ['period' => $period, 'academic_year_id' => $year?->id, 'academic_term_id' => $termId, 'grade_id' => $filters['grade_id'] ?? null, 'section_id' => $filters['section_id'] ?? null, 'student_id' => $filters['student_id'] ?? null]) }}" target="_blank" rel="noopener">Print report cards</a>@endif</div>
    </div>

    <form method="GET" action="{{ route('admin.report-cards.generate') }}" class="admin-report-card-filters panel" data-report-card-filters>
        <label><span>Report period</span><select name="period" required><option value="partial" @selected($period === 'partial')>Partial / academic term</option><option value="annual" @selected($period === 'annual')>Annual / full year</option></select></label>
        <label><span>Academic year</span><select name="academic_year_id" data-report-card-year required>@foreach($years as $yearOption)<option value="{{ $yearOption->id }}" @selected((int) ($filters['academic_year_id'] ?? $year?->id) === (int) $yearOption->id)>{{ $yearOption->name }}</option>@endforeach</select></label>
        <label><span>Academic term</span><select name="academic_term_id" data-report-card-term @disabled($period === 'annual')><option value="">Select term</option>@foreach($allTerms as $termOption)<option value="{{ $termOption->id }}" data-year="{{ $termOption->academic_year_id }}" @selected((int) ($termId ?? 0) === (int) $termOption->id)>{{ $termOption->name }}</option>@endforeach</select></label>
        <label><span>Class</span><select name="grade_id"><option value="">All classes</option>@foreach($grades as $gradeOption)<option value="{{ $gradeOption->id }}" @selected((int) ($filters['grade_id'] ?? 0) === (int) $gradeOption->id)>{{ $gradeOption->name }}</option>@endforeach</select></label>
        <label><span>Section</span><select name="section_id"><option value="">All sections</option>
            @foreach($grades as $gradeOption)
                @foreach($gradeOption->sections as $sectionOption)
                    <option value="{{ $sectionOption->id }}" @selected((int) ($filters['section_id'] ?? 0) === (int) $sectionOption->id)>{{ $gradeOption->name }} / {{ $sectionOption->name }}</option>
                @endforeach
            @endforeach
        </select></label>
        <label><span>Student</span><x-student-picker name="student_id" :academic-year-id="$year?->id" :grade-id="$filters['grade_id'] ?? null" :section-id="$filters['section_id'] ?? null" :selected-id="$filters['student_id'] ?? null" :selected-label="$registrations->firstWhere('student_id', $filters['student_id'] ?? null)?->student?->full_name" placeholder="Type student name or number" /></label>
        <button class="btn btn-primary" type="submit">Generate report cards</button>
    </form>
    <p class="mt-3 text-sm text-slate-500">Only grades with status <strong>Published</strong> or <strong>Locked</strong> are included. Draft and submitted-for-review grades never appear on an official report card.</p>

    @if($generated)
        @if($cards->isNotEmpty())
            <div class="admin-report-card-results">@include('report-card.cards')</div>
        @else
            <div class="panel mt-5 p-8 text-center text-slate-500">No published or locked grades match the selected period and student filters.</div>
        @endif
    @else
        <div class="panel admin-report-card-empty-state"><strong>Select a period to generate an official report card.</strong><span>Partial cards use one academic term. Annual cards combine all terms in the selected academic year.</span></div>
    @endif
</section>
<script>
document.querySelector('[data-report-card-filters] select[name="period"]')?.addEventListener('change', (event) => {
    const term = document.querySelector('[data-report-card-term]');
    if (term) term.disabled = event.target.value === 'annual';
});
const year = document.querySelector('[data-report-card-year]');
const term = document.querySelector('[data-report-card-term]');
const syncTerms = () => {
    if (!year || !term) return;
    [...term.options].forEach((option) => {
        if (!option.dataset.year) return;
        option.hidden = option.dataset.year !== year.value;
        if (option.hidden && option.selected) term.value = '';
    });
};
year?.addEventListener('change', syncTerms);
syncTerms();
</script>
@endsection
