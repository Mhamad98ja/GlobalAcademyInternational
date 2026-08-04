@extends('layouts.app', ['title' => 'Invoices'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Invoices</h1>
        <p class="page-subtitle">Student balances are derived from valid payments and refunds.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('distributions.create') }}">New distribution</a>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="grid w-full gap-2 sm:grid-cols-[minmax(0,1fr)_12rem_auto_auto]">
            <input name="search" value="{{ request('search') }}" placeholder="Invoice number or student" aria-label="Search invoices">
            <select name="status" aria-label="Filter by payment status">
                <option value="">All payment statuses</option>
                @foreach (['unpaid', 'partially_paid', 'paid', 'deferred'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                @endforeach
            </select>
            <button class="btn btn-secondary">Filter</button>
            <a class="btn btn-secondary" href="{{ route('invoices.index') }}">Reset</a>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table min-w-[760px]">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Student</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Balance</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td><a class="link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                        <td>{{ $invoice->student->full_name }}</td>
                        <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                        <td>{{ number_format($invoice->total, 2) }}</td>
                        <td class="font-medium {{ $invoice->balance_due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($invoice->balance_due, 2) }}</td>
                        <td>
                            <span class="badge {{ $invoice->payment_status === 'paid' ? 'badge-green' : ($invoice->payment_status === 'deferred' ? 'badge-blue' : 'badge-amber') }}">{{ str_replace('_', ' ', $invoice->payment_status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-200 px-4 py-3">{{ $invoices->links() }}</div>
</section>
@endsection
