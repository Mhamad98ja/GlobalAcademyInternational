@php
    $schoolName = $school['school_name'] ?? 'Global Academy International';
    $schoolContact = collect([$school['school_address'] ?? null, $school['school_phone'] ?? null])->filter()->implode(' • ');
@endphp
@foreach($cards as $card)
    @php($registration = $card['registration'])
    <article class="official-report-card {{ $forPrint ? 'official-report-card-print' : '' }}">
        <header class="official-report-card-header">
            <div class="official-report-card-brand">
                <img src="{{ asset('images/global-academy-international-logo.png') }}" alt="{{ $schoolName }} logo">
                <div><h1>{{ $schoolName }}</h1>@if($schoolContact)<p>{{ $schoolContact }}</p>@endif</div>
            </div>
            <div class="official-report-card-title"><strong>{{ $period === 'annual' ? 'Annual Report Card' : 'Partial Report Card' }}</strong><span>{{ $year?->name ?? 'Academic year' }}{{ $period === 'partial' && $periodTerms->first() ? ' · '.$periodTerms->first()->name : '' }}</span></div>
        </header>
        <section class="official-report-card-student">
            <div><span>Student</span><strong>{{ $registration->student?->full_name }}</strong></div>
            <div><span>Student number</span><strong>{{ $registration->student?->student_number }}</strong></div>
            <div><span>Class</span><strong>{{ $registration->grade?->name ?? '—' }}{{ $registration->section ? ' / '.$registration->section->name : '' }}</strong></div>
            <div><span>Grade average</span><strong>{{ $card['overallAverage'] !== null ? number_format($card['overallAverage'], 2).'%' : '—' }}</strong></div>
        </section>
        <table class="official-report-card-table">
            <thead><tr><th>Subject</th>@foreach($periodTerms as $term)<th>{{ $term->name }}</th>@endforeach<th>Earned grade</th><th>Average</th><th>Assessments</th><th>Teacher</th></tr></thead>
            <tbody>
                @forelse($card['subjects'] as $subject)
                    <tr>
                        <td><strong>{{ $subject['name'] }}</strong></td>
                        @foreach($periodTerms as $term)<td class="official-report-card-number">{{ $subject['termAverages'][(string) $term->id] !== null ? number_format($subject['termAverages'][(string) $term->id], 2).'%' : '—' }}</td>@endforeach
                        <td class="official-report-card-number">{{ number_format($subject['earned'], 2) }} / {{ number_format($subject['maximum'], 2) }}</td>
                        <td class="official-report-card-number">{{ $subject['average'] !== null ? number_format($subject['average'], 2).'%' : '—' }}</td>
                        <td><details><summary>{{ $subject['assessmentCount'] }} recorded</summary><ul>@foreach($subject['entries'] as $entry)<li>{{ $entry->assessment?->name }}: {{ rtrim(rtrim(number_format((float) $entry->score, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) ($entry->assessment?->maximum_score ?? 0), 2), '0'), '.') }}</li>@endforeach</ul></details></td>
                        <td>{{ $subject['teachers'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + $periodTerms->count() }}" class="official-report-card-empty">No published or locked grades are available for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
        <footer class="official-report-card-footer"><span>Prepared from published and locked grades</span><span>School stamp / signature: ____________________</span></footer>
    </article>
@endforeach
