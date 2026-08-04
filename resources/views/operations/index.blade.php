@extends('layouts.app', ['title' => 'Operations Center'])

@section('content')
<div class="page-header">
    <div>
        <div class="text-xs font-semibold uppercase tracking-[.1em] text-teal-700">Unified management</div>
        <h1 class="page-title">Operations Center</h1>
        <p class="page-subtitle">One place to review supplier purchases, supplier returns, student invoices and book returns.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($canViewPurchases)<a class="btn btn-secondary" href="{{ route('purchases.create') }}">New supplier purchase</a>@endif
        @if (auth()->user()->hasPermission('distributions.create'))<a class="btn btn-primary" href="{{ route('distributions.create') }}">New distribution</a>@endif
        @if ($canViewReturns)<a class="btn btn-secondary" href="{{ route('invoices.index') }}">Find invoice for return</a>@endif
        @if ($canViewSupplierReturns)<a class="btn btn-secondary" href="{{ route('purchases.index') }}">Find purchase to return</a>@endif
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="grid w-full gap-2 sm:grid-cols-[11rem_minmax(0,1fr)_auto]">
            <select name="type" aria-label="Transaction type">
                <option value="">All available operations</option>
                @if ($canViewPurchases)<option value="purchase" @selected($type === 'purchase')>Supplier purchases</option>@endif
                @if ($canViewInvoices)<option value="invoice" @selected($type === 'invoice')>Student invoices</option>@endif
                @if ($canViewReturns)<option value="return" @selected($type === 'return')>Book returns</option>@endif
                @if ($canViewSupplierReturns)<option value="supplier_return" @selected($type === 'supplier_return')>Supplier returns</option>@endif
            </select>
            <input name="search" value="{{ $search }}" placeholder="Search reference, supplier or student" aria-label="Search operations">
            <button class="btn btn-secondary">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data-table min-w-[900px]">
            <thead><tr><th>Type</th><th>Reference</th><th>Supplier / student</th><th>Date</th><th>Total / credit / refund</th><th>Balance</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td><span class="badge {{ $transaction['type'] === 'purchase' ? 'badge-blue' : ($transaction['type'] === 'invoice' ? 'badge-green' : 'badge-amber') }}">{{ $transaction['type_label'] }}</span></td>
                        <td><a class="link" href="{{ $transaction['href'] }}">{{ $transaction['reference'] }}</a><div class="text-xs text-slate-500">{{ $transaction['detail'] }}</div></td>
                        <td>{{ $transaction['party'] }}</td>
                        <td>{{ $transaction['date']->format('d M Y') }}</td>
                        <td class="{{ $transaction['type'] === 'return' ? 'text-rose-700' : ($transaction['type'] === 'supplier_return' ? 'text-emerald-700' : '') }}">{{ $transaction['type'] === 'return' ? 'Refund: ' : ($transaction['type'] === 'supplier_return' ? 'Credit: ' : '') }}{{ number_format((float) $transaction['total'], 2) }}</td>
                        <td>@if ($transaction['balance'] !== null)<span class="{{ $transaction['balance'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format((float) $transaction['balance'], 2) }}</span>@else - @endif</td>
                        <td><span class="badge {{ in_array($transaction['status'], ['paid', 'accepted', 'completed'], true) ? 'badge-green' : 'badge-amber' }}">{{ str($transaction['status'])->headline() }}</span></td>
                        <td><a class="link text-xs" href="{{ $transaction['href'] }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No matching operations are available with your current access.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-4 py-3">{{ $transactions->links() }}</div>
</section>
@endsection
