@extends('layouts.app', ['title' => $report['title']])

@section('content')
<section class="dars-student-report-page">
    @if($reportKey === 'dynamic')
        <div class="dars-dynamic-title">Student &amp; Family Report</div>
        <div class="dars-dynamic-report">
            <div class="dars-dynamic-layout">
                <fieldset class="dars-fields-panel">
                    <legend><span>Fields</span><small>4 selected</small></legend>
                    <div class="dars-field-groups">
                        <strong>STUDENT</strong>
                        @foreach(['Student Name','Student Name (Arabic)','Student Code','Gender','Date of Birth','Birth Place','Birth Place (Arabic)','Student Email','College Email','Student Mobile','Entry Date','ID Number','Nationality','Nationality 2','Nationality 3','Religion','Birth Country'] as $field)
                            <label><input type="checkbox" @checked(in_array($field, ['Student Name','Student Code','Gender']))> {{ $field }}</label>
                        @endforeach
                        <strong>CLASS</strong>
                        @foreach(['School Year','Class','Class (Arabic)','Class (French)','Section','Room','Activity Center','Registered','Registration Date','Has Left','Left Reason','Left Reason Details','Left Date','Remarks'] as $field)
                            <label><input type="checkbox" @checked($field === 'Class')> {{ $field }}</label>
                        @endforeach
                        <strong>FAMILY</strong>
                        @foreach(['Father Name','Father Phone','Mother Name','Mother Phone','Guardian Name','Family Code','Register Town','Register Number','Register Town'] as $field)
                            <label><input type="checkbox"> {{ $field }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="dars-dynamic-right">
                    <fieldset>
                        <legend>Report Type</legend>
                        <div class="flex gap-2"><button type="button" class="btn btn-primary">Per Student</button><button type="button" class="btn btn-secondary">Per Family</button><label class="ml-2 flex items-center gap-2"><input type="checkbox"> Include sibling names</label></div>
                        <p>One row per student. Enable &ldquo;Include sibling names&rdquo; to add a column listing that student's siblings.</p>
                    </fieldset>
                    <fieldset>
                        <legend><span>Filters</span><button type="button" class="dars-add-condition">+ Add condition</button></legend>
                        <div class="dars-filter-condition"><span>1</span><select><option>School Year</option><option>Student Name</option><option>Gender</option><option>Class</option></select><select><option>Is any of</option><option>Is none of</option></select><select><option>Current academic year</option></select><button type="button" aria-label="Remove filter">&times;</button></div>
                    </fieldset>
                    <fieldset class="dars-preview-panel">
                        <legend><span>Preview</span><small>Not run yet</small><a class="btn btn-secondary btn-small" href="{{ route('reports.index', $report['items'][0][3]) }}">Preview</a><a class="btn btn-secondary btn-small" href="{{ route('reports.csv', $report['items'][0][3]) }}">Export to Excel</a></legend>
                        <div>Configure your report, then click Preview.</div>
                    </fieldset>
                </div>
            </div>
        </div>
    @else
        <form method="GET" class="dars-report-search"><input type="search" name="q" value="{{ request('q') }}" placeholder="Search" aria-label="Search reports"><button type="submit" aria-label="Search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button></form>
        <div class="dars-report-list">
            @forelse($report['items'] as [$label, $description, $routeName, $params])
                <article><div><h2>{{ $label }}</h2><p>{{ $description }}</p></div><a href="{{ route($routeName, $params) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="m9.5 12 1.7 1.7 3.8-4"/></svg> View Report</a></article>
            @empty
                <p class="empty">No reports match your search.</p>
            @endforelse
        </div>
    @endif
</section>
@endsection
