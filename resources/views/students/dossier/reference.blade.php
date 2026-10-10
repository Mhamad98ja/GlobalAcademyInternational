@extends('layouts.app', ['title' => $reference['title']])

@section('content')
<section class="dars-reference-page">
    <div class="dars-breadcrumb"><a href="{{ route('students.references.show', ['reference' => 'codes']) }}">Administration / References</a><span>&gt;</span><strong>{{ $reference['title'] }}</strong></div>

    @if($referenceKey === 'codes')
        <div class="flex flex-wrap items-end gap-3">
        <form method="GET" class="dars-reference-filter">
            <label>Code Type</label>
            <select name="type" onchange="this.form.submit()">
                @foreach($codeCategories as $category)
                    <option value="{{ $category }}" @selected($selectedCodeType === $category)>{{ $codeTypes[$category]['label'] ?? str($category)->headline() }}</option>
                @endforeach
            </select>
        </form>
        @if($canManageReferences)
            <a class="dars-add-new" href="{{ route('students.references.create', ['reference' => 'codes']) }}">Add New Code</a>
            <a class="btn btn-secondary btn-small" href="{{ route('students.references.create', ['reference' => 'codes', 'category' => 1]) }}">Add New Category</a>
        @endif
        </div>
    @elseif($referenceKey === 'classes')
        <form method="GET" class="dars-reference-filter dars-reference-filter-row">
            <label>School Year<select name="academic_year">@foreach($years as $year)<option value="{{ $year->id }}" @selected((string) request('academic_year') === (string) $year->id)>{{ $year->name }}</option>@endforeach</select></label>
            <label>Code<input name="q" value="{{ request('q') }}" placeholder="Enter code"></label>
            <button class="dars-reference-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>Search</button>
        </form>
    @elseif($referenceKey === 'towns')
        <form method="GET" class="dars-reference-filter dars-reference-filter-row">
            <label>District<select name="district"><option value="">--</option>@foreach($districts as $district)<option value="{{ $district }}" @selected(request('district') === $district)>{{ $district }}</option>@endforeach</select></label>
            <label>Search<input name="q" value="{{ request('q') }}"></label>
            <button class="dars-admin-search" aria-label="Search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
        </form>
    @endif

    <div class="dars-reference-table-wrap">
        <table class="dars-reference-table">
            <thead>
                <tr>
                    @foreach($reference['headers'] as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                    @if($canManageReferences)
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach($row['values'] as $value)
                            <td>@if($referenceKey === 'classes' && $loop->index === 3)<input type="checkbox" disabled @checked((bool) $value)>@else{{ $value }}@endif</td>
                        @endforeach
                        @if($canManageReferences)
                            <td class="dars-row-actions">
                                <a class="edit" href="{{ route('students.references.edit', ['reference' => $referenceKey, 'entry' => $row['key']]) }}" aria-label="Edit">Edit</a>
                                @if($row['deletable'] ?? true)
                                    <form method="POST" action="{{ route('students.references.destroy', ['reference' => $referenceKey, 'entry' => $row['key']]) }}" class="inline" onsubmit="return confirm('Delete this reference? Existing records that use this value will be cleared where applicable.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="delete" aria-label="Delete">Delete</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ count($reference['headers']) + 1 }}" class="empty">No reference values yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->lastPage() > 1)
        <nav class="dars-reference-pager" aria-label="Reference pages">
            <a href="{{ $rows->url(1) }}" aria-label="First page">&lt;&lt;</a>
            <a href="{{ $rows->previousPageUrl() ?: $rows->url(1) }}" aria-label="Previous page">&lt;</a>
            @foreach(range(1, $rows->lastPage()) as $page)
                @if($page === $rows->currentPage())<strong>{{ $page }}</strong>@else<a href="{{ $rows->url($page) }}">{{ $page }}</a>@endif
            @endforeach
            <a href="{{ $rows->nextPageUrl() ?: $rows->url($rows->lastPage()) }}" aria-label="Next page">&gt;</a>
            <a href="{{ $rows->url($rows->lastPage()) }}" aria-label="Last page">&gt;&gt;</a>
        </nav>
    @endif

    @if($canManageReferences && $referenceKey !== 'codes')
        <a class="dars-add-new" href="{{ route('students.references.create', ['reference' => $referenceKey]) }}">Add New</a>
    @endif
</section>
@endsection
