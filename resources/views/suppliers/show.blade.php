@extends('layouts.app', ['title' => $supplier->name])

@section('content')
<div class="page-header">
    <div>
        <div class="text-sm text-slate-500"><a class="link" href="{{ route('suppliers.index') }}">Suppliers</a> / Detail</div>
        <h1 class="page-title mt-1">{{ $supplier->name }}</h1>
        <p class="page-subtitle">{{ $supplier->code }} &middot; {{ $supplier->phone ?: 'No phone' }}</p>
    </div>
    <div class="flex gap-2"><a class="btn btn-secondary" href="{{ route('suppliers.edit', $supplier) }}">Edit</a><a class="btn btn-primary" href="{{ route('purchases.create') }}">New purchase</a></div>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="stat"><div class="stat-label">Opening balance</div><div class="stat-value">{{ number_format((float) $supplier->opening_balance, 2) }}</div></div>
    <div class="stat"><div class="stat-label">Supplier invoices</div><div class="stat-value">{{ number_format((float) $supplier->purchases->sum('total'), 2) }}</div></div>
    <div class="stat"><div class="stat-label">Balance due to supplier</div><div class="stat-value {{ $supplier->account_balance > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format((float) $supplier->account_balance, 2) }}</div></div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1.7fr]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Supplier details</h2></div>
        <dl class="divide-y divide-slate-100 px-5">
            @foreach (['Contact' => $supplier->contact_person, 'Email' => $supplier->email, 'Terms' => $supplier->payment_terms, 'Tax number' => $supplier->tax_number] as $label => $value)
                <div class="flex justify-between gap-4 py-3"><dt class="muted">{{ $label }}</dt><dd class="text-right font-medium text-slate-800">{{ $value ?: '—' }}</dd></div>
            @endforeach
        </dl>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Supplier invoice history</h2></div>
        <div class="table-wrap">
            <table class="data-table min-w-[700px]">
                <thead><tr><th>Purchase</th><th>Date</th><th>Total</th><th>Paid</th><th>Remaining</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($supplier->purchases as $purchase)
                        <tr>
                            <td><a class="link" href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a><div class="text-xs text-slate-500">{{ $purchase->supplier_invoice_number ?: 'No invoice number' }}</div></td>
                            <td>{{ $purchase->purchase_date->format('d M Y') }}</td>
                            <td>{{ number_format((float) $purchase->total, 2) }}</td>
                            <td class="text-emerald-700">{{ number_format((float) $purchase->amount_paid, 2) }}</td>
                            <td class="{{ $purchase->balance_due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format((float) $purchase->balance_due, 2) }}</td>
                            <td><span class="badge {{ $purchase->payment_status === 'paid' ? 'badge-green' : 'badge-amber' }}">{{ str($purchase->payment_status)->headline() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No purchases yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
