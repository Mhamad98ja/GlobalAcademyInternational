@extends('layouts.app', ['title' => $purchase->purchase_number])

@section('content')
@php($hasOutstandingItems = $purchase->items->contains(fn ($item) => $item->quantity_received < $item->quantity_ordered))
<div class="page-header">
    <div>
        <div class="text-sm text-slate-500"><a class="link" href="{{ route('purchases.index') }}">Supplier purchases</a> / Detail</div>
        <h1 class="page-title mt-1">{{ $purchase->purchase_number }}</h1>
        <p class="page-subtitle">{{ $purchase->supplier->name }} &middot; {{ $purchase->purchase_date->format('d M Y') }}@if ($purchase->supplier_invoice_number) &middot; Invoice {{ $purchase->supplier_invoice_number }}@endif</p>
    </div>
    <span class="badge badge-blue">{{ str($purchase->status)->headline() }}</span>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="stat"><div class="stat-label">Supplier invoice total</div><div class="stat-value">{{ number_format((float) $purchase->total, 2) }}</div></div>
    <div class="stat"><div class="stat-label">Paid to supplier</div><div class="stat-value text-emerald-700">{{ number_format((float) $purchase->amount_paid, 2) }}</div></div>
    <div class="stat"><div class="stat-label">Remaining balance</div><div class="stat-value {{ $purchase->balance_due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format((float) $purchase->balance_due, 2) }}</div></div>
</div>

@if ($hasOutstandingItems)
    <section class="mb-6 mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5">
        <h2 class="font-semibold text-amber-900">Historic purchase with outstanding stock</h2>
        <p class="mt-1 text-sm text-amber-800">This purchase was created before automatic stock receipt. Use one action to receive every remaining quantity; there are no separate quantity inputs.</p>
        <form method="POST" action="{{ route('purchases.receive-outstanding', $purchase) }}" class="mt-4" data-submit-lock>
            @csrf
            <button class="btn btn-primary">Receive all outstanding stock</button>
        </form>
    </section>
@endif

<section class="panel mt-6">
    <div class="panel-header"><h2 class="panel-title">Purchase items</h2><span class="badge {{ $purchase->payment_status === 'paid' ? 'badge-green' : 'badge-amber' }}">{{ str($purchase->payment_status)->headline() }}</span></div>
    <div class="table-wrap">
        <table class="data-table min-w-[720px]">
            <thead><tr><th>Book</th><th>Purchased</th><th>Added to stock</th><th>Unit purchase price</th><th>Discount</th><th>Sale price</th><th>Total</th></tr></thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    @php($discountPercentage = $item->quantity_ordered * $item->unit_price > 0 ? ($item->discount_amount / ($item->quantity_ordered * $item->unit_price)) * 100 : 0)
                    <tr>
                        <td>{{ $item->book->title }}</td>
                        <td>{{ $item->quantity_ordered }}</td>
                        <td>{{ $item->quantity_received }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>{{ number_format((float) $discountPercentage, 2) }}%</td>
                        <td>{{ number_format((float) $item->book->selling_price, 2) }}</td>
                        <td>{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="panel mt-6">
    <div class="panel-header"><h2 class="panel-title">Invoice totals</h2></div>
    <dl class="grid grid-cols-2 divide-x divide-slate-100 sm:grid-cols-4">
        <div class="p-4"><dt class="muted">Subtotal</dt><dd class="mt-1 text-lg font-semibold">{{ number_format((float) $purchase->subtotal, 2) }}</dd></div>
        <div class="p-4"><dt class="muted">Discount</dt><dd class="mt-1 text-lg font-semibold">{{ number_format((float) $purchase->discount_total, 2) }}</dd></div>
        <div class="p-4"><dt class="muted">Tax</dt><dd class="mt-1 text-lg font-semibold">{{ number_format((float) $purchase->tax_total, 2) }}</dd></div>
        <div class="p-4"><dt class="muted">Total</dt><dd class="mt-1 text-lg font-semibold">{{ number_format((float) $purchase->total, 2) }}</dd></div>
    </dl>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,1fr)]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Supplier payment history</h2></div>
        <div class="table-wrap">
            <table class="data-table min-w-[620px]">
                <thead><tr><th>Date</th><th>Method</th><th>Reference / cheque no.</th><th>Amount</th><th>Recorded by</th></tr></thead>
                <tbody>
                    @forelse ($purchase->payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_on->format('d M Y') }}</td>
                            <td>{{ str($payment->method)->headline() }}</td>
                            <td>{{ $payment->reference_number ?: '—' }}</td>
                            <td class="text-emerald-700">{{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ $payment->receivedBy?->full_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No payment recorded for this supplier invoice.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Record supplier payment</h2></div>
        @if ($purchase->balance_due > 0)
            <form method="POST" action="{{ route('purchases.payments.store', $purchase) }}" class="space-y-3 p-5" data-submit-lock>
                @csrf
                <div><label class="form-label">Amount</label><input type="number" min="0.01" max="{{ $purchase->balance_due }}" step="0.01" name="amount" value="{{ old('amount') }}" required></div>
                <div><label class="form-label">Payment date</label><input type="date" name="paid_on" value="{{ old('paid_on', today()->format('Y-m-d')) }}" required></div>
                <div><label class="form-label">Method</label><select name="method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="check">Check</option><option value="other">Other</option></select></div>
                <div><label class="form-label">Reference / cheque number</label><input name="reference_number" value="{{ old('reference_number') }}" placeholder="Optional payment number"></div>
                <button class="btn btn-primary w-full">Record payment</button>
            </form>
        @else
            <div class="empty">This supplier invoice is fully paid.</div>
        @endif
    </section>
</div>
@endsection
