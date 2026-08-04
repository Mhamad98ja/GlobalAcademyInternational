@extends('layouts.app', ['title' => 'Reports'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Review, print, or export the operational data available to your role.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ route('reports.print', ['report' => $report]) }}">Print report</a>
        <a class="btn btn-primary" href="{{ route('reports.csv', ['report' => $report]) }}">Export CSV</a>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="flex w-full max-w-lg gap-2">
            <label class="sr-only" for="report">Report type</label>
            <select id="report" name="report" onchange="this.form.submit()">
                <option value="students" @selected($report === 'students')>Student registrations</option>
                <option value="stock" @selected($report === 'stock')>Stock on hand</option>
                <option value="invoices" @selected($report === 'invoices')>Invoices</option>
                <option value="payments" @selected($report === 'payments')>Payments</option>
            </select>
            <noscript><button class="btn btn-secondary">View</button></noscript>
        </form>
    </div>
    @include('reports.table')
</section>
@endsection
