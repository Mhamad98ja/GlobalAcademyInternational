@extends('layouts.app', ['title' => 'Reports'])

@section('content')
@php($reportQuery = array_merge(request()->except('page'), ['report' => $report]))
@php($canSelectReportRows = $selectableReportRows ?? false)
<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Choose a report, then filter by the information that belongs to it.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ route('reports.print', $reportQuery) }}">Print report</a>
        @if ($canSelectReportRows)
            <button type="button" class="btn btn-secondary" data-print-selected-students data-print-url="{{ route('reports.print') }}" data-selection-key="{{ $selectionKey }}" data-report="{{ $report }}">Print selected records</button>
        @endif
        <a class="btn btn-primary" href="{{ route('reports.csv', $reportQuery) }}">Export CSV</a>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="grid w-full gap-2 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="form-label" for="report">Report type</label>
                <select id="report" name="report" onchange="this.form.submit()">
                    <option value="student_information" @selected($report === 'student_information')>Student general information</option>
                    <option value="class_sections" @selected($report === 'class_sections')>Students by class and section</option>
                    <option value="students" @selected($report === 'students')>Student registrations</option>
                    <option value="stock" @selected($report === 'stock')>Stock on hand</option>
                    @if ($canViewFinancials)<option value="invoices" @selected($report === 'invoices')>Invoices</option>@endif
                    <option value="distributions" @selected($report === 'distributions')>Book distributions</option>
                    @if ($canViewFinancials)<option value="payments" @selected($report === 'payments')>Payments</option>@endif
                </select>
            </div>

            @if ($report === 'stock')
                <div>
                    <label class="form-label" for="report-search">Search books</label>
                    <input id="report-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Title, SKU or barcode">
                </div>
                <div>
                    <label class="form-label" for="stock-level">Stock level</label>
                    <select id="stock-level" name="stock_level">
                        <option value="">All stock levels</option>
                        <option value="in_stock" @selected(($filters['stock_level'] ?? '') === 'in_stock')>In stock</option>
                        <option value="low_stock" @selected(($filters['stock_level'] ?? '') === 'low_stock')>Low stock</option>
                        <option value="out_of_stock" @selected(($filters['stock_level'] ?? '') === 'out_of_stock')>Out of stock</option>
                    </select>
                </div>
            @elseif ($report === 'student_information')
                <div>
                    <label class="form-label" for="report-search">Search students</label>
                    <input id="report-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, student no., phone or email">
                </div>
                <div>
                    <label class="form-label" for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">All genders</option>
                        <option value="male" @selected(($filters['gender'] ?? '') === 'male')>Male</option>
                        <option value="female" @selected(($filters['gender'] ?? '') === 'female')>Female</option>
                    </select>
                </div>
                <fieldset class="md:col-span-2 xl:col-span-4 rounded-md border border-slate-200 bg-slate-50 p-3">
                    <input type="hidden" name="student_fields_selection" value="1">
                    <legend class="px-1 text-sm font-medium text-slate-700">Student information to include</legend>
                    <p class="mb-2 text-xs text-slate-500">Choose the columns for the report, printout, and CSV export.</p>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($studentInformationFieldOptions as $field => $label)
                            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="student_fields[]" value="{{ $field }}" @checked(in_array($field, $filters['student_fields'] ?? array_keys($studentInformationFieldOptions), true))>{{ $label }}</label>
                        @endforeach
                    </div>
                    @error('student_fields')<p class="form-error">{{ $message }}</p>@enderror
                </fieldset>
            @else
                <div>
                    <label class="form-label" for="report-search">{{ $report === 'invoices' ? 'Search invoices or students' : ($report === 'distributions' ? ($canViewFinancials ? 'Search distributions, invoices or students' : 'Search distributions or students') : ($report === 'payments' ? 'Search payments, invoices or students' : 'Search students')) }}</label>
                    <input id="report-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $report === 'invoices' ? 'Invoice number or student' : ($report === 'distributions' ? ($canViewFinancials ? 'Invoice number or student' : 'Student name or number') : ($report === 'payments' ? 'Payment number, invoice or student' : 'Student name or registration no.')) }}">
                </div>
                <div>
                    <label class="form-label" for="academic-year">Academic year</label>
                    <select id="academic-year" name="academic_year_id">
                        <option value="">All academic years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="report-grade">Grade</label>
                    <select id="report-grade" name="grade_id" data-grade-select data-section-target="#report-section">
                        <option value="">All grades</option>
                        @foreach ($grades as $grade)
                            <option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? null) == $grade->id)>{{ $grade->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="report-section">Section</label>
                    <select id="report-section" name="section_id" disabled>
                        <option value="">All sections</option>
                        @foreach ($grades as $grade)
                            @foreach ($grade->sections as $section)
                                <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(($filters['section_id'] ?? null) == $section->id)>{{ $grade->name }}.{{ $section->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                @if (in_array($report, ['students', 'class_sections'], true))
                    <div>
                        <label class="form-label" for="registration-status">Registration status</label>
                        <select id="registration-status" name="registration_status">
                            <option value="">All registration statuses</option>
                            @foreach (['active', 'inactive', 'withdrawn', 'completed'] as $status)
                                <option value="{{ $status }}" @selected(($filters['registration_status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($report === 'invoices')
                    <div>
                        <label class="form-label" for="invoice-status">Payment status</label>
                        <select id="invoice-status" name="invoice_payment_status">
                            <option value="">All payment statuses</option>
                            @foreach (['unpaid', 'partially_paid', 'paid', 'deferred'] as $status)
                                <option value="{{ $status }}" @selected(($filters['invoice_payment_status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($report === 'payments')
                    <div>
                        <label class="form-label" for="payment-method">Payment method</label>
                        <select id="payment-method" name="payment_method">
                            <option value="">All methods</option>
                            @foreach (['cash', 'card', 'bank_transfer', 'check', 'other'] as $method)
                                <option value="{{ $method }}" @selected(($filters['payment_method'] ?? '') === $method)>{{ str($method)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="payment-status">Payment status</label>
                        <select id="payment-status" name="payment_status">
                            <option value="">All payment statuses</option>
                            @foreach (['valid', 'reversed'] as $status)
                                <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($report !== 'class_sections')
                    <div>
                        <label class="form-label" for="date-from">{{ $report === 'students' ? 'Registered from' : 'Date from' }}</label>
                        <input id="date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div>
                        <label class="form-label" for="date-to">{{ $report === 'students' ? 'Registered to' : 'Date to' }}</label>
                        <input id="date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                @endif
            @endif

            <div class="flex items-end gap-2">
                <button class="btn btn-secondary flex-1">Filter</button>
                <a class="btn btn-secondary" href="{{ route('reports.index', ['report' => $report]) }}">Reset</a>
            </div>
        </form>
    </div>
    @include('reports.table')
</section>

@if ($canSelectReportRows)
<script>
const selectAllReportRows = document.querySelector('[data-select-all-report-rows]');
const selectedReportRowsCount = document.querySelector('[data-selected-report-rows-count]');
const selectedReportRows = () => [...document.querySelectorAll('[data-report-row-select]')];
const refreshSelectedReportRows = () => {
    const selected = selectedReportRows().filter((input) => input.checked);
    if (selectedReportRowsCount) selectedReportRowsCount.textContent = `${selected.length} selected`;
    if (selectAllReportRows) selectAllReportRows.checked = selected.length > 0 && selected.length === selectedReportRows().length;
};
selectAllReportRows?.addEventListener('change', () => {
    selectedReportRows().forEach((input) => { input.checked = selectAllReportRows.checked; });
    refreshSelectedReportRows();
});
selectedReportRows().forEach((input) => input.addEventListener('change', refreshSelectedReportRows));
document.querySelector('[data-print-selected-students]')?.addEventListener('click', (event) => {
    const selectedIds = selectedReportRows().filter((input) => input.checked).map((input) => input.value);
    if (selectedIds.length === 0) {
        window.alert('Select at least one record to print.');
        return;
    }
    const printUrl = new URL(event.currentTarget.dataset.printUrl, window.location.origin);
    const parameters = new URLSearchParams(window.location.search);
    const selectionKey = event.currentTarget.dataset.selectionKey;
    parameters.delete('page');
    parameters.delete(`${selectionKey}[]`);
    parameters.delete(selectionKey);
    parameters.set('report', event.currentTarget.dataset.report);
    selectedIds.forEach((selectedId) => parameters.append(`${selectionKey}[]`, selectedId));
    printUrl.search = parameters.toString();
    window.open(printUrl.toString(), '_blank', 'noopener');
});
refreshSelectedReportRows();
</script>
@endif
@endsection
