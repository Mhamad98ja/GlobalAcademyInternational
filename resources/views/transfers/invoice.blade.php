@extends('layouts.app', ['title' => 'Print '.$invoice->invoice_number])

@section('content')
@php
    $schoolName = $school['school_name'] ?? 'Global Academy International';
    $schoolContact = collect([$school['school_address'] ?? null, $school['school_phone'] ?? null])->filter()->implode(' • ');
@endphp

@include('components.print-document-styles')

<div class="no-print mb-5 flex flex-wrap justify-between gap-2">
    <a class="btn btn-secondary" href="{{ route('transfers.show', $transfer) }}">Back to transfer</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Print internal invoice</button>
</div>

<article class="print-document print-document--invoice">
    <header class="print-masthead">
        <div class="print-brand">
            @include('components.print-school-mark')
            <div>
                <div class="print-school-name">{{ $schoolName }}</div>
                @if ($schoolContact)<div class="print-school-contact">{{ $schoolContact }}</div>@endif
            </div>
        </div>
        <div class="print-document-kind">Inventory office<br>Inter-branch invoice</div>
    </header>

    <div class="print-document-body">
        <div class="print-title-row">
            <div>
                <h1 class="print-title">Internal Branch Transfer Invoice</h1>
                <div class="print-title-note">Valued stock movement. This document is not a student sale, customer invoice, or receivable.</div>
            </div>
            <div class="print-reference">
                <div class="print-reference-row"><span>Invoice no.</span><strong>{{ $invoice->invoice_number }}</strong></div>
                <div class="print-reference-row"><span>Transfer no.</span><strong>{{ $transfer->transfer_number }}</strong></div>
                <div class="print-reference-row"><span>Issue date</span><strong>{{ $invoice->issued_on->format('d M Y') }}</strong></div>
                <div class="print-reference-row"><span>Status</span><strong>{{ str($invoice->status)->headline() }}</strong></div>
            </div>
        </div>

        <section class="print-info-grid">
            <div>
                <div class="print-info-label">From branch</div>
                <div class="print-info-value">{{ $invoice->sourceBranch?->name }}</div>
                <div class="print-info-detail">{{ $invoice->sourceBranch?->code }}</div>
            </div>
            <div>
                <div class="print-info-label">To branch</div>
                <div class="print-info-value">{{ $invoice->destinationBranch?->name }}</div>
                <div class="print-info-detail">{{ $invoice->destinationBranch?->code }}</div>
            </div>
        </section>

        <table class="print-table">
            <thead><tr><th>Book / item</th><th>SKU</th><th class="print-quantity">Qty</th><th class="print-number">Unit price</th><th class="print-number">Tax</th><th class="print-number">Line total</th></tr></thead>
            <tbody>@foreach ($invoice->items as $item)<tr><td>{{ $item->description }}</td><td>{{ $item->sku ?: $item->book?->sku }}</td><td class="print-quantity">{{ $item->quantity }}</td><td class="print-number">{{ number_format((float) $item->unit_price, 2) }}</td><td class="print-number">{{ number_format((float) $item->line_tax, 2) }}</td><td class="print-number">{{ number_format((float) $item->line_total, 2) }}</td></tr>@endforeach</tbody>
        </table>

        <div class="print-financial-summary">
            <div class="print-notes"><div class="print-notes-title">Movement note</div><p>{{ $invoice->notes ?: 'Stock delivered between branches. Receiving branch confirmation is required.' }}</p><p>Inventory value: {{ $invoice->sourceBranch?->name }} −{{ number_format((float) $invoice->total, 2) }}; {{ $invoice->destinationBranch?->name }} +{{ number_format((float) $invoice->total, 2) }}. No customer revenue or receivable is created.</p>@if($invoice->status === 'voided')<p>Voided {{ $invoice->voided_at?->format('d M Y H:i') }} by {{ $invoice->voidedBy?->name ?? 'System' }}. {{ $invoice->void_reason }}</p>@endif</div>
            <div class="print-total-list"><div class="print-total-row"><span>Subtotal</span><strong>{{ number_format((float) $invoice->subtotal, 2) }}</strong></div><div class="print-total-row"><span>Tax</span><strong>{{ number_format((float) $invoice->tax_total, 2) }}</strong></div><div class="print-total-row print-total-row--emphasis"><span>Total transfer value</span><strong>{{ number_format((float) $invoice->total, 2) }}</strong></div></div>
        </div>

        <div class="print-signatures"><div class="print-signature">Prepared by: {{ $invoice->issuedBy?->name ?? $transfer->createdBy?->name ?? 'System' }}</div><div class="print-signature">Sending branch acknowledgement</div><div class="print-signature">Receiving branch acknowledgement</div></div>
        <footer class="print-document-footer"><span>{{ $schoolContact ?: 'School book inventory office' }}</span><span>Generated {{ now()->format('d M Y, H:i') }}</span></footer>
    </div>
</article>
@endsection
