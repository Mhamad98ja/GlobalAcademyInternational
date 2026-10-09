@extends('layouts.app', ['title' => $definition['title']])

@php
    $columns = $catalog ? array_keys($catalog[0]) : [];
    $samePage = fn (string $target): string => route('admin.dars.show', ['page' => $target]);
@endphp

@section('content')
<section class="dars-administration-page">
    <div class="dars-breadcrumb"><a href="{{ route('admin.users') }}">Administration</a><span>&gt;</span><strong>{{ $definition['title'] }}</strong></div>

    @if($page === 'school-year')
        <div class="dars-admin-card-grid dars-school-year-actions">
            @foreach([
                ['Prepare next school year', 'calendar', 'blue'], ['Prepare the archived previous school year', 'archive', 'amber'],
                ['Promote students to the next class', 'move', 'dark'], ['Remove unregistered students', 'delete', 'red'],
                ['Copy grading configuration from last year', 'copy', 'green'], ['Copy skills configuration from last year', 'copy', 'green'],
                ['Copy standards configuration', 'copy', 'green'], ['Copy tuition configuration', 'copy', 'indigo'],
            ] as $action)
                <button type="button" class="dars-admin-tile dars-admin-tile-{{ $action[2] }}"><strong>{{ $action[0] }}</strong>@if($action[1] === 'move')<span class="dars-admin-tile-controls"><label><input type="checkbox" checked> Keep the current year</label><select><option>Section --</option></select><select><option>Registered</option><option>Not registered</option></select></span>@endif</button>
            @endforeach
        </div>
        <div class="dars-admin-grid-wrap"><table class="dars-admin-grid"><thead><tr><th>School year</th><th>Current</th><th>Registration open</th><th></th></tr></thead><tbody>@forelse($years as $year)<tr><td>{{ $year->name }}</td><td><input type="checkbox" @checked($year->status === 'open') disabled></td><td><input type="checkbox" @checked($year->status !== 'closed') disabled></td><td><button type="button" class="dars-grid-action">Select</button><button type="button" class="dars-grid-icon">Edit</button></td></tr>@empty<tr><td colspan="4">No school years found.</td></tr>@endforelse</tbody></table></div>

    @elseif($page === 'menu')
        <div class="dars-admin-filterbar"><label>Application<select><option>Activities</option><option>Student Profile</option><option>Report Card</option></select></label></div>
        <div class="dars-admin-grid-wrap"><table class="dars-admin-grid"><thead><tr>@foreach($columns as $column)<th>{{ $column }}</th>@endforeach<th></th><th></th></tr></thead><tbody>@foreach($catalog as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach<td><button type="button" class="dars-grid-icon">Edit</button></td><td><button type="button" class="dars-grid-icon danger">Delete</button></td></tr>@endforeach</tbody></table></div><button type="button" class="dars-outline-add">Add</button>

    @elseif($page === 'pages')
        <div class="dars-admin-filterbar"><label>Application<select><option>Activities</option></select></label><label>Module<select><option></option><option>Activity</option><option>Student</option></select></label><button type="button" class="dars-search-button">Search</button><button type="button" class="dars-reload-button">Reload</button></div>
        <div class="dars-admin-grid-wrap"><table class="dars-admin-grid"><thead><tr>@foreach($columns as $column)<th>{{ $column }}</th>@endforeach<th></th></tr></thead><tbody>@foreach($catalog as $row)<tr>@foreach($columns as $column)<td>{{ $row[$column] }}</td>@endforeach<td><button type="button" class="dars-grid-icon">Edit</button></td></tr>@endforeach</tbody></table></div>

    @elseif($page === 'report-header')
        @foreach([['Header (Portrait / Top)', '840px x 80px', ''], ['Header (Landscape / Side)', '80px x 1188px', 'Landscape ']] as [$heading, $size, $kind])
            <div class="dars-admin-header-section"><h2>{{ $heading }}</h2><div class="dars-admin-info">Image size must be <strong>{{ $size }}</strong></div><div class="dars-admin-header-preview"><strong>{{ $school['name'] }}</strong><small>--</small></div><div class="dars-admin-toolbar"><button type="button" class="dars-danger-button">Delete {{ $kind }}Header</button><button type="button" class="dars-outline-button">Download {{ $kind }}Header</button></div><div class="dars-file-row"><input type="file">@if($kind === '')<button type="button" class="dars-primary-button">Upload Header</button><button type="button" class="dars-dark-button">Generate Default</button>@endif</div></div>
        @endforeach

    @elseif($page === 'configuration')
        <form method="GET" action="{{ $samePage('configuration') }}" class="dars-admin-config-filters"><label>Application<select><option></option><option>INS</option><option>ADM</option></select></label><label>Configuration name<input name="q" value="{{ $query }}"></label><label>Configuration key<input name="q" value="{{ $query }}"></label><button class="dars-search-button" aria-label="Search">Search</button></form>
        <div class="dars-admin-grid-wrap"><table class="dars-admin-grid"><thead><tr><th>App code</th><th>Configuration name</th><th>Configuration key</th><th>Configuration value</th><th></th><th></th></tr></thead><tbody>@forelse($settings as $setting)<tr><td>ADM</td><td>{{ $setting->key }}</td><td>{{ $setting->key }}</td><td>{{ $setting->value }}</td><td><button type="button" class="dars-value-link">Value</button></td><td><button type="button" class="dars-grid-icon">Edit</button></td></tr>@empty<tr><td colspan="6">No configuration found.</td></tr>@endforelse</tbody></table></div>

    @elseif($page === 'dynamic-fields')
        <div class="dars-admin-filterbar"><label>Table<select><option>Parents</option><option>Students</option><option>Teachers</option></select></label></div><div class="dars-admin-empty-space"></div>

    @elseif(in_array($page, ['dictionary', 'family-type', 'file-category', 'mail-type'], true))
        <div class="dars-admin-grid-wrap"><table class="dars-admin-grid"><thead><tr>@foreach($columns as $column)<th>{{ $column }}</th>@endforeach<th></th><th></th></tr></thead><tbody>@foreach($catalog as $row)<tr>@foreach($columns as $column)<td>@if($column === 'Color')<span class="dars-color-swatch" style="background:{{ $row[$column] }}"></span>@else{{ $row[$column] }}@endif</td>@endforeach<td><button type="button" class="dars-grid-icon">Edit</button></td><td><button type="button" class="dars-grid-icon danger">Delete</button></td></tr>@endforeach</tbody></table></div><div class="dars-admin-pager">First &nbsp; Previous <strong>1</strong> Next &nbsp; Last</div>@if(in_array($page, ['family-type', 'file-category'], true))<button type="button" class="dars-outline-add">Add {{ $page === 'file-category' ? 'new' : '' }}</button>@endif

    @elseif($page === 'family-remark-type')
        <div class="dars-admin-empty-card">No data found.<button type="button" class="dars-outline-add">Add</button></div>

    @elseif($page === 'colleges')
        <div class="dars-college-card"><button type="button" class="dars-primary-button">Edit</button><dl><div><dt>School:</dt><dd>{{ $school['name'] }}</dd><dt>Primary name:</dt><dd>{{ $school['name'] }}</dd></div><div><dt>Secondary name:</dt><dd>--</dd><dt>School address:</dt><dd>{{ $school['address'] }}</dd></div><div><dt>Postal code:</dt><dd>BP</dd><dt>Phone number:</dt><dd>{{ $school['phone'] }}</dd></div><div><dt>Principal:</dt><dd>----</dd><dt>Email:</dt><dd>--</dd></div></dl></div>

    @elseif($page === 'import')
        <div class="dars-import-card"><div class="dars-file-row"><input type="file"><button type="button" class="dars-outline-button">Load File</button></div><hr><label>Sheets<select><option></option></select></label><select><option></option></select><button type="button" class="dars-outline-button">Load File</button></div>
    @endif
</section>
@endsection
