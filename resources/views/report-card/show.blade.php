@extends('layouts.app', ['title' => $definition['title'] ?? $report])

@php
    $valueFor = static fn (array $field): mixed => request($field['name']) ?? $field['default'] ?? null;
@endphp

@section('content')
<section class="dars-report-card-page">
    @if(empty($definition['home']))
        <form method="GET" action="{{ route('report-card.show', ['report' => $reportKey]) }}" class="dars-report-card-form {{ !empty($definition['statistical']) ? 'dars-report-card-statistical' : '' }}">
            @foreach($definition['fields'] ?? [] as $field)
                @if($field['type'] === 'heading')<h2 class="dars-report-card-heading">{{ $field['label'] }}</h2>
                @elseif($field['type'] === 'checkbox')<label class="dars-report-card-checkbox"><input type="checkbox" name="{{ $field['name'] }}" value="1" @checked((bool) request($field['name'], $field['checked'] ?? false))><span>{{ $field['label'] }}</span></label>
                @elseif(in_array($field['type'], ['configure', 'ok'], true))<button type="button" class="dars-report-card-inline-button {{ $field['type'] === 'configure' ? 'muted' : '' }}">{{ $field['label'] }}</button>
                @else
                    <label class="dars-report-card-field {{ $field['type'] === 'search' ? 'is-search' : '' }} {{ $field['label'] === '' ? 'is-blank-label' : '' }}">@if($field['label'] !== '')<span>{{ $field['label'] }}</span>@endif
                        @if($field['type'] === 'year')<select name="academic_year_id">@foreach($years as $yearOption)<option value="{{ $yearOption->id }}" @selected((string) $valueFor($field) === (string) $yearOption->id || (!$valueFor($field) && $loop->first))>{{ $yearOption->name }}</option>@endforeach</select>
                        @elseif($field['type'] === 'term')<select name="academic_term_id"><option value=""></option>@foreach($terms as $termOption)<option value="{{ $termOption->id }}" @selected((string) $valueFor($field) === (string) $termOption->id)>{{ $termOption->name }}</option>@endforeach</select>
                        @elseif($field['type'] === 'grade')<select name="grade_id"><option value=""></option>@foreach($grades as $gradeOption)<option value="{{ $gradeOption->id }}" @selected((string) $valueFor($field) === (string) $gradeOption->id)>{{ $gradeOption->name }}</option>@endforeach</select>
                        @elseif($field['type'] === 'section')<select name="section_id"><option value=""></option>@foreach($grades as $gradeOption)@foreach($gradeOption->sections as $sectionOption)<option value="{{ $sectionOption->id }}" @selected((string) $valueFor($field) === (string) $sectionOption->id)>{{ $sectionOption->name }}</option>@endforeach@endforeach</select>
                        @elseif($field['type'] === 'select')<select name="{{ $field['name'] }}"><option value=""></option>@foreach($field['options'] ?? [] as $optionValue => $optionLabel)<option value="{{ $optionValue }}" @selected((string) $valueFor($field) === (string) $optionValue)>{{ $optionLabel }}</option>@endforeach</select>
                        @elseif($field['type'] === 'number')<input type="number" name="{{ $field['name'] }}" value="{{ $valueFor($field) }}">
                        @else<input type="search" name="{{ $field['name'] }}" value="{{ $valueFor($field) }}" placeholder="{{ $field['label'] !== '' ? '' : 'Search...' }}">@endif
                    </label>
                @endif
            @endforeach
            <button type="submit" class="dars-report-card-submit">{{ $definition['button'] ?? 'Search' }}</button>
        </form>
        @if($reportKey === 'report-card')
            @if($reportCard)
                <section class="dars-report-card-official">
                    <header><div><h1>Official report card</h1><p>{{ $reportCard['student']->full_name }} · {{ $reportCard['student']->student_number }}</p></div><div><strong>{{ $reportCard['student']->registrations->first()?->grade?->name ?? '' }}</strong><span>{{ $terms->firstWhere('id', $reportCard['termId'])?->name ?? '' }}</span></div></header>
                    <table><thead><tr><th>Subject</th><th>Evaluation</th><th>Type</th><th>Grade</th><th>Average</th><th>Status</th></tr></thead><tbody>
                        @forelse($reportCard['subjects'] as $subject)
                            @foreach($subject['entries'] as $entry)
                                <tr><td>{{ $subject['name'] }}</td><td>{{ $entry->assessment?->name }}</td><td>{{ str($entry->assessment?->type)->headline() }}</td><td>{{ rtrim(rtrim(number_format((float) $entry->score, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $entry->assessment?->maximum_score, 2), '0'), '.') }}</td><td>{{ number_format($subject['average'], 2) }}%</td><td>{{ $entry->status === 'locked' ? 'Locked' : 'Published' }}</td></tr>
                            @endforeach
                        @empty
                            <tr><td colspan="6">No official grades are published for this student and academic term.</td></tr>
                        @endforelse
                    </tbody></table>
                </section>
            @else
                <div class="dars-report-card-empty">Select an academic year, academic term, class, and student to generate the official report card.</div>
            @endif
        @endif
        @if(!empty($definition['viewer']) || !empty($definition['loading']) || !empty($definition['empty']))
            <div class="dars-report-card-viewer"><div class="dars-report-card-viewer-toolbar" aria-label="Report viewer toolbar"><span>Previous | <input aria-label="Page number" value="" readonly> of 0 | Next</span><span>Refresh <select aria-label="Zoom"><option>100%</option></select> Print</span></div>@if(!empty($definition['loading']))<div class="dars-report-card-loading"><strong>Loading...</strong><span>Cancel</span></div>@elseif(!empty($definition['empty']))<div class="dars-report-card-empty">{{ $definition['empty'] }}</div>@else<div class="dars-report-card-viewer-blank"></div>@endif</div>
        @endif
    @endif
</section>
@endsection
