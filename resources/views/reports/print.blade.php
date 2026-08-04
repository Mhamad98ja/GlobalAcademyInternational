@extends('layouts.app', ['title' => 'Print report'])

@php
    $reportTitles = [
        'students' => 'Student registration report',
        'stock' => 'Book stock report',
        'invoices' => 'Student invoice report',
        'payments' => 'Payment collection report',
    ];
@endphp

@section('content')
@php
    $reportQuery = array_merge(request()->except('page'), ['report' => $report]);
    $schoolName = $school['school_name'] ?? config('app.name', 'School Book Manager');
    $schoolContact = collect([$school['school_address'] ?? null, $school['school_phone'] ?? null])->filter()->implode(' • ');
@endphp

@include('components.print-document-styles')

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Print report</h1>
        <p class="page-subtitle">Use your browser print dialog to print or save this report as a PDF.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="{{ route('reports.index', $reportQuery) }}">Back to reports</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print report</button>
    </div>
</div>

<article class="print-document">
    <header class="print-masthead">
        <div class="print-brand">
            @include('components.print-school-mark')
            <div>
                <div class="print-school-name">{{ $schoolName }}</div>
                @if ($schoolContact)<div class="print-school-contact">{{ $schoolContact }}</div>@endif
            </div>
        </div>
        <div class="print-document-kind">School operations<br>Official report</div>
    </header>

    <div class="print-document-body">
        <div class="print-title-row">
            <div>
                <h1 class="print-title">{{ $reportTitles[$report] }}</h1>
                <div class="print-title-note">Prepared {{ now()->format('d M Y, H:i') }} by {{ auth()->user()->name }}</div>
            </div>
            <div class="print-reference">
                <div class="print-reference-row"><span>Report type</span><strong>{{ str($report)->headline() }}</strong></div>
                <div class="print-reference-row"><span>Records</span><strong>{{ number_format($rows->count()) }}</strong></div>
                <div class="print-reference-row"><span>Printed on</span><strong>{{ now()->format('d M Y') }}</strong></div>
            </div>
        </div>

        @if ($filterSummary)
            <div class="print-filter-summary"><strong>Applied filters:</strong> @foreach ($filterSummary as $filter)<span>{{ $filter }}</span>@endforeach</div>
        @else
            <div class="print-filter-summary"><strong>Applied filters:</strong> <span>All available records</span></div>
        @endif

        @include('reports.table')

        <div class="print-signatures">
            <div class="print-signature">Prepared by</div>
            <div class="print-signature">Checked by</div>
            <div class="print-signature">Approved by</div>
        </div>

        <footer class="print-document-footer">
            <span>{{ $schoolContact ?: 'School book inventory and distribution office' }}</span>
            <span>Internal school record</span>
        </footer>
    </div>
</article>
@endsection
