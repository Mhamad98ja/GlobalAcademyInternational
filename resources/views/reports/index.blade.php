@extends('layouts.app', ['title' => 'Reports'])

@section('content')
@php($reportQuery = array_merge(request()->except('page'), ['report' => $report]))
<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Choose a report, then filter by the information that belongs to it.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ route('reports.print', $reportQuery) }}">Print report</a>
        <a class="btn btn-primary" href="{{ route('reports.csv', $reportQuery) }}">Export CSV</a>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="grid w-full gap-2 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="form-label" for="report">Report type</label>
                <select id="report" name="report" onchange="this.form.submit()">
                    <option value="students" @selected($report === 'students')>Student registrations</option>
                    <option value="stock" @selected($report === 'stock')>Stock on hand</option>
                    <option value="invoices" @selected($report === 'invoices')>Invoices</option>
                    <option value="payments" @selected($report === 'payments')>Payments</option>
                </select>
            </div>

            @if ($report === 'stock')
                <div>
                    <label class="form-label">Search books</label>
                    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Title, SKU or barcode">
                </div>
                <div>
                    <label class="form-label">Stock level</label>
                    <select name="stock_level">
                        <option value="">All stock levels</option>
                        <option value="in_stock" @selected(($filters['stock_level'] ?? '') === 'in_stock')>In stock</option>
                        <option value="low_stock" @selected(($filters['stock_level'] ?? '') === 'low_stock')>Low stock</option>
                        <option value="out_of_stock" @selected(($filters['stock_level'] ?? '') === 'out_of_stock')>Out of stock</option>
                    </select>
                </div>
            @else
                <div>
                    <label class="form-label">{{ $report === 'students' ? 'Search students' : ($report === 'invoices' ? 'Search invoices or students' : 'Search payments, invoices or students') }}</label>
                    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $report === 'students' ? 'Student name or registration no.' : ($report === 'invoices' ? 'Invoice number or student' : 'Payment number, invoice or student') }}">
                </div>
                <div>
                    <label class="form-label">Academic year</label>
                    <select name="academic_year_id">
                        <option value="">All academic years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Grade</label>
                    <select name="grade_id" data-grade-select data-section-target="#report-section">
                        <option value="">All grades</option>
                        @foreach ($grades as $grade)
                            <option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? null) == $grade->id)>{{ $grade->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <select id="report-section" name="section_id" disabled>
                        <option value="">All sections</option>
                        @foreach ($grades as $grade)
                            @foreach ($grade->sections as $section)
                                <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(($filters['section_id'] ?? null) == $section->id)>{{ $grade->name }}.{{ $section->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                @if ($report === 'students')
                    <div>
                        <label class="form-label">Registration status</label>
                        <select name="registration_status">
                            <option value="">All registration statuses</option>
                            @foreach (['active', 'inactive', 'withdrawn', 'completed'] as $status)
                                <option value="{{ $status }}" @selected(($filters['registration_status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($report === 'invoices')
                    <div>
                        <label class="form-label">Payment status</label>
                        <select name="invoice_payment_status">
                            <option value="">All payment statuses</option>
                            @foreach (['unpaid', 'partially_paid', 'paid', 'deferred'] as $status)
                                <option value="{{ $status }}" @selected(($filters['invoice_payment_status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="form-label">Payment method</label>
                        <select name="payment_method">
                            <option value="">All methods</option>
                            @foreach (['cash', 'card', 'bank_transfer', 'check', 'other'] as $method)
                                <option value="{{ $method }}" @selected(($filters['payment_method'] ?? '') === $method)>{{ str($method)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Payment status</label>
                        <select name="payment_status">
                            <option value="">All payment statuses</option>
                            @foreach (['valid', 'reversed'] as $status)
                                <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="form-label">{{ $report === 'students' ? 'Registered from' : 'Date from' }}</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div>
                    <label class="form-label">{{ $report === 'students' ? 'Registered to' : 'Date to' }}</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
            @endif

            <div class="flex items-end gap-2">
                <button class="btn btn-secondary flex-1">Filter</button>
                <a class="btn btn-secondary" href="{{ route('reports.index', ['report' => $report]) }}">Reset</a>
            </div>
        </form>
    </div>
    @include('reports.table')
</section>
@endsection
