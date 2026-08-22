@extends('layouts.app', ['title' => 'Book distribution'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Book distribution</h1>
        <p class="page-subtitle">Issue books to students using barcode scanning.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('distributions.create') }}">New distribution</a>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="grid w-full gap-2 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="form-label" for="distribution-search">Search</label>
                <input id="distribution-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student, distribution or invoice">
            </div>
            <div>
                <label class="form-label" for="distribution-year">Academic year</label>
                <select id="distribution-year" name="academic_year_id">
                    <option value="">All academic years</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="distribution-grade">Grade</label>
                <select id="distribution-grade" name="grade_id" data-grade-select data-section-target="#distribution-section">
                    <option value="">All grades</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? null) == $grade->id)>{{ $grade->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="distribution-section">Section</label>
                <select id="distribution-section" name="section_id" disabled>
                    <option value="">All sections</option>
                    @foreach ($grades as $grade)
                        @foreach ($grade->sections as $section)
                            <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(($filters['section_id'] ?? null) == $section->id)>{{ $grade->name }}.{{ $section->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="distribution-date-from">Distributed from</label>
                <input id="distribution-date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label class="form-label" for="distribution-date-to">Distributed to</label>
                <input id="distribution-date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="flex items-end gap-2">
                <button class="btn btn-secondary flex-1">Filter</button>
                <a class="btn btn-secondary" href="{{ route('distributions.index') }}">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Distribution</th><th>Student</th>@if ($canViewFinancials)<th>Invoice</th>@endif<th>Date</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($distributions as $distribution)
                    <tr>
                        <td><a class="link" href="{{ route('distributions.show', $distribution) }}">{{ substr($distribution->public_id, 0, 8) }}</a></td>
                        <td>{{ $distribution->student->full_name }}</td>
                        @if ($canViewFinancials)<td><a class="link" href="{{ route('invoices.show', $distribution->invoice) }}">{{ $distribution->invoice->invoice_number }}</a></td>@endif
                        <td>{{ $distribution->distributed_on->format('d M Y') }}</td>
                        <td><span class="badge badge-green">{{ ucfirst($distribution->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canViewFinancials ? 5 : 4 }}" class="empty">No books have been distributed yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-4 py-3">{{ $distributions->links() }}</div>
</section>
@endsection
