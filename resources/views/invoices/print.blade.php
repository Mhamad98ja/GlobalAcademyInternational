@extends('layouts.app', ['title' => 'Print '.$invoice->invoice_number])

@section('content')
@php
    $schoolName = $school['school_name'] ?? config('app.name', 'School Book Manager');
    $schoolContact = collect([$school['school_address'] ?? null, $school['school_phone'] ?? null])->filter()->implode(' • ');
    $registration = $invoice->registration;
    $gradeName = $registration?->grade?->name ?? $invoice->grade?->name ?? '-';
    $sectionName = $registration?->section?->name ?? $invoice->section?->name;
    $academicYear = $registration?->academicYear?->name ?? '-';
@endphp

@include('components.print-document-styles')

<div class="no-print mb-5 flex flex-wrap justify-between gap-2">
    <a class="btn btn-secondary" href="{{ route('invoices.show', $invoice) }}">Back to invoice</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Print invoice</button>
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
        <div class="print-document-kind">Accounts office<br>Student book invoice</div>
    </header>

    <div class="print-document-body">
        <div class="print-title-row">
            <div>
                <h1 class="print-title">Student Book Invoice</h1>
                <div class="print-title-note">Books and learning materials issued for the current academic year.</div>
            </div>
            <div class="print-reference">
                <div class="print-reference-row"><span>Invoice no.</span><strong>{{ $invoice->invoice_number }}</strong></div>
                <div class="print-reference-row"><span>Issue date</span><strong>{{ $invoice->invoice_date->format('d M Y') }}</strong></div>
                <div class="print-reference-row"><span>Payment status</span><strong>{{ str($invoice->payment_status)->headline() }}</strong></div>
            </div>
        </div>

        <section class="print-info-grid">
            <div>
                <div class="print-info-label">Billed to</div>
                <div class="print-info-value">{{ $invoice->student->full_name }}</div>
                <div class="print-info-detail">Student no. {{ $invoice->student->student_number }}</div>
                @if ($invoice->responsiblePerson)<div class="print-info-detail">Responsible person: {{ $invoice->responsiblePerson->full_name }}</div>@endif
            </div>
            <div>
                <div class="print-info-label">Academic placement</div>
                <div class="print-info-value">{{ $academicYear }}</div>
                <div class="print-info-detail">{{ $gradeName }}@if ($sectionName) · Section {{ $sectionName }}@endif</div>
            </div>
        </section>

        <table class="print-table">
            <thead><tr><th>Book / item</th><th class="print-quantity">Qty</th><th class="print-number">Unit price</th><th class="print-number">Total</th></tr></thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="print-quantity">{{ $item->quantity }}</td>
                        <td class="print-number">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="print-number">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="print-financial-summary">
            <div class="print-notes">
                <div class="print-notes-title">Notes</div>
                <p>{{ $invoice->notes ?: 'Please retain this invoice for your school records.' }}</p>
            </div>
            <div class="print-total-list">
                <div class="print-total-row"><span>Subtotal</span><strong>{{ number_format((float) $invoice->subtotal, 2) }}</strong></div>
                <div class="print-total-row"><span>Discount</span><strong>{{ number_format((float) $invoice->discount_total, 2) }}</strong></div>
                <div class="print-total-row"><span>Tax</span><strong>{{ number_format((float) $invoice->tax_total, 2) }}</strong></div>
                <div class="print-total-row"><span>Paid</span><strong>{{ number_format((float) $invoice->amount_paid, 2) }}</strong></div>
                <div class="print-total-row print-total-row--emphasis"><span>Balance due</span><strong>{{ number_format((float) $invoice->balance_due, 2) }}</strong></div>
            </div>
        </div>

        <div class="print-signatures">
            <div class="print-signature">Prepared by</div>
            <div class="print-signature">Checked by</div>
            <div class="print-signature">Parent / guardian acknowledgement</div>
        </div>

        <footer class="print-document-footer">
            <span>{{ $schoolContact ?: 'School book inventory and distribution office' }}</span>
            <span>Generated {{ now()->format('d M Y, H:i') }}</span>
        </footer>
    </div>
</article>
@endsection
