@extends('layouts.app', ['title' => $definition['title'] ?? $report])

@php
    $reportGroups = [
        'Reports' => ['report-card', 'individual-transcript', 'remarks-summary', 'general-transcript', 'remarks-detail', 'progress-transcript', 'subjects-list-per-class', 'transcript-by-subject-choice', 'student-ranking-per-subject', 'subjects-transcript', 'official-statement', 'official-statement-mehe', 'student-performance', 'subject-averages-per-class', 'failure-per-subject', 'underachievers-by-subject', 'annual-progression-of-class-average', 'top-5-students-per-division', 'highest-grade-per-subject', 'export-bacc-francais', 'best-annual-columns-transcript'],
        'Statistical Reports' => ['students-transcript', 'grade-distribution', 'class-average-evolution', 'statistical-calculation', 'results-comparison-table'],
    ];
    $valueFor = static fn (array $field): mixed => request($field['name']) ?? $field['default'] ?? null;
@endphp

@section('content')
<section class="dars-report-card-page">
    <div class="dars-breadcrumb"><a href="{{ route('report-card.show', ['report' => 'report-card']) }}">Reports</a><span>&gt;</span><strong>{{ $definition['title'] ?? $report }}</strong></div>
    @if(!empty($definition['home']))
        <div class="dars-report-home">@foreach($reportGroups as $groupTitle => $groupReports)<section class="dars-report-home-group" aria-labelledby="report-group-{{ $loop->index }}"><h1 id="report-group-{{ $loop->index }}">{{ $groupTitle }}</h1><div class="dars-report-home-list">@foreach($groupReports as $groupReport)@continue($groupReport === 'report-card')@if(isset($definitions[$groupReport]))<a href="{{ route('report-card.show', ['report' => $groupReport]) }}"><span aria-hidden="true">[]</span>{{ $definitions[$groupReport]['title'] }}</a>@endif@endforeach</div></section>@endforeach</div>
    @else
        <form method="GET" action="{{ route('report-card.show', ['report' => $reportKey]) }}" class="dars-report-card-form {{ !empty($definition['statistical']) ? 'dars-report-card-statistical' : '' }}">
            @foreach($definition['fields'] ?? [] as $field)
                @if($field['type'] === 'heading')<h2 class="dars-report-card-heading">{{ $field['label'] }}</h2>
                @elseif($field['type'] === 'checkbox')<label class="dars-report-card-checkbox"><input type="checkbox" name="{{ $field['name'] }}" value="1" @checked((bool) request($field['name'], $field['checked'] ?? false))><span>{{ $field['label'] }}</span></label>
                @elseif(in_array($field['type'], ['configure', 'ok'], true))<button type="button" class="dars-report-card-inline-button {{ $field['type'] === 'configure' ? 'muted' : '' }}">{{ $field['label'] }}</button>
                @else
                    <label class="dars-report-card-field {{ $field['type'] === 'search' ? 'is-search' : '' }} {{ $field['label'] === '' ? 'is-blank-label' : '' }}">@if($field['label'] !== '')<span>{{ $field['label'] }}</span>@endif
                        @if($field['type'] === 'year')<select name="academic_year_id">@foreach($years as $yearOption)<option value="{{ $yearOption->id }}" @selected((string) $valueFor($field) === (string) $yearOption->id || (!$valueFor($field) && $loop->first))>{{ $yearOption->name }}</option>@endforeach</select>
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
        @if(!empty($definition['viewer']) || !empty($definition['loading']) || !empty($definition['empty']))
            <div class="dars-report-card-viewer"><div class="dars-report-card-viewer-toolbar" aria-label="Report viewer toolbar"><span>Previous | <input aria-label="Page number" value="" readonly> of 0 | Next</span><span>Refresh <select aria-label="Zoom"><option>100%</option></select> Print</span></div>@if(!empty($definition['loading']))<div class="dars-report-card-loading"><strong>Loading...</strong><span>Cancel</span></div>@elseif(!empty($definition['empty']))<div class="dars-report-card-empty">{{ $definition['empty'] }}</div>@else<div class="dars-report-card-viewer-blank"></div>@endif</div>
        @endif
    @endif
</section>
@endsection
