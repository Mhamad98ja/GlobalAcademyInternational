@extends('layouts.app', ['title' => $invoice->invoice_number])

@section('content')
<div class="page-header">
    <div>
        <div class="text-sm text-slate-500"><a class="link" href="{{ route('invoices.index') }}">Invoices</a> / Detail</div>
        <h1 class="page-title mt-1">{{ $invoice->invoice_number }}</h1>
        <p class="page-subtitle">{{ $invoice->student->full_name }} - {{ $invoice->invoice_date->format('d M Y') }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a class="btn btn-secondary" target="_blank" href="{{ route('invoices.print', ['invoice' => $invoice, 'copy' => 'financial']) }}">Print financial copy</a>
        <a class="btn btn-secondary" target="_blank" href="{{ route('invoices.print', ['invoice' => $invoice, 'copy' => 'student']) }}">Print parent/student copy</a>
        @if ($invoice->status !== 'cancelled' && auth()->user()->hasPermission('returns.manage'))
            <a class="btn btn-secondary" href="{{ route('returns.form', $invoice) }}">Return books</a>
        @endif
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="stat">
        <div class="stat-label">Invoice total</div>
        <div class="stat-value">{{ number_format($invoice->total, 2) }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Paid</div>
        <div class="stat-value text-emerald-700">{{ number_format($invoice->amount_paid, 2) }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Balance due</div>
        <div class="stat-value {{ $invoice->balance_due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($invoice->balance_due, 2) }}</div>
    </div>
</div>

<section class="panel mt-6">
    <div class="panel-header">
        <h2 class="panel-title">Invoice items</h2>
        <span class="badge badge-blue">{{ str_replace('_', ' ', $invoice->payment_status) }}</span>
    </div>
    <div class="table-wrap">
        <table class="data-table min-w-[680px]">
            <thead>
                <tr><th>Description</th><th>Quantity</th><th>Unit price</th><th>Tax</th><th>Total</th></tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->unit_price, 2) }}</td>
                        <td>{{ number_format($item->tax_amount, 2) }}</td>
                        <td>{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-slate-50">
                    <td colspan="4" class="px-4 py-3 text-right font-semibold">Total</td>
                    <td class="px-4 py-3 font-semibold">{{ number_format($invoice->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,1fr)]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Payment history</h2></div>
        <div class="table-wrap">
            <table class="data-table min-w-[640px]">
                <thead>
                    <tr><th>Payment</th><th>Date</th><th>Method</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($invoice->payments as $payment)
                        <tr>
                            <td><a class="link" href="{{ route('payments.receipt', $payment) }}">{{ $payment->payment_number }}</a></td>
                            <td>{{ $payment->payment_date->format('d M Y') }}</td>
                            <td class="capitalize">{{ str_replace('_', ' ', $payment->method) }}</td>
                            <td>{{ number_format($payment->amount, 2) }}</td>
                            <td><span class="badge {{ $payment->status === 'valid' ? 'badge-green' : 'badge-red' }}">{{ $payment->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No payment recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Record payment</h2></div>
        @if ($invoice->balance_due > 0 && $invoice->status !== 'cancelled' && auth()->user()->hasPermission('payments.create'))
            <form method="POST" action="{{ route('payments.store', $invoice) }}" class="space-y-3 p-5" data-submit-lock>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div>
                    <label class="form-label">Amount</label>
                    <input type="number" min="0.01" max="{{ $invoice->balance_due }}" step="0.01" name="amount" required>
                </div>
                <div>
                    <label class="form-label">Date</label>
                    <input type="date" name="payment_date" value="{{ today()->format('Y-m-d') }}" required>
                </div>
                <div>
                    <label class="form-label">Method</label>
                    <select name="method">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank transfer</option>
                        <option value="check">Check</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Reference</label>
                    <input name="reference_number">
                </div>
                <button class="btn btn-primary w-full">Record payment</button>
            </form>
        @else
            <div class="empty">No payment can be recorded for this invoice.</div>
        @endif
    </section>
</div>

@if ($invoice->status !== 'cancelled' && auth()->user()->hasPermission('invoices.cancel'))
    <section class="panel mt-6">
        <div class="panel-header"><h2 class="panel-title text-rose-800">Cancel invoice</h2></div>
        <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="flex flex-col gap-3 p-5 sm:flex-row" data-submit-lock>
            @csrf
            <input name="reason" required placeholder="Cancellation reason">
            <button class="btn btn-danger shrink-0">Cancel invoice</button>
        </form>
    </section>
@endif
@endsection
