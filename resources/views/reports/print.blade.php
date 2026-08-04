@extends('layouts.app', ['title' => 'Print report'])

@php
    $reportTitles = [
        'students' => 'Student registrations',
        'stock' => 'Stock on hand',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
    ];
@endphp

@section('content')
<div class="page-header no-print">
    <div>
        <h1 class="page-title">Print report</h1>
        <p class="page-subtitle">Use your browser print dialog to print or save this report as a PDF.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="{{ route('reports.index', ['report' => $report]) }}">Back to reports</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
    </div>
</div>

<article class="panel print-report">
    <div class="border-b border-slate-200 px-5 py-5">
        <div class="text-xs font-semibold uppercase tracking-[.12em] text-teal-700">School Book Manager</div>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ $reportTitles[$report] }}</h1>
        <p class="mt-1 text-sm text-slate-500">Generated {{ now()->format('d M Y, H:i') }}</p>
    </div>
    @include('reports.table')
</article>
@endsection
