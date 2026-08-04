@extends('layouts.app', ['title' => 'Supplier purchases'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Supplier purchases</h1>
        <p class="page-subtitle">Each purchase is a supplier invoice. Record payments against it to keep supplier balances accurate.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('purchases.create') }}">New purchase</a>
</div>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table min-w-[760px]">
            <thead>
                <tr><th>Purchase</th><th>Supplier</th><th>Date</th><th>Total</th><th>Paid</th><th>Remaining</th><th>Payment status</th></tr>
            </thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td>
                            <a class="link" href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a>
                            <div class="text-xs text-slate-500">{{ $purchase->supplier_invoice_number ? 'Invoice: '.$purchase->supplier_invoice_number : 'No supplier invoice number' }}</div>
                        </td>
                        <td>{{ $purchase->supplier->name }}</td>
                        <td>{{ $purchase->purchase_date->format('d M Y') }}</td>
                        <td>{{ number_format((float) $purchase->total, 2) }}</td>
                        <td class="text-emerald-700">{{ number_format((float) $purchase->amount_paid, 2) }}</td>
                        <td class="{{ $purchase->balance_due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format((float) $purchase->balance_due, 2) }}</td>
                        <td><span class="badge {{ $purchase->payment_status === 'paid' ? 'badge-green' : 'badge-amber' }}">{{ str($purchase->payment_status)->headline() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No supplier purchases yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-4 py-3">{{ $purchases->links() }}</div>
</section>
@endsection
