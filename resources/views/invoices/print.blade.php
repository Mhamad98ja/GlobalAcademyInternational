@extends('layouts.app', ['title' => 'Print '.$invoice->invoice_number])

@section('content')
<div class="no-print mb-5">
    <a class="btn btn-primary" href="#" onclick="window.print();return false">Print</a>
</div>

<article class="panel mx-auto max-w-4xl p-5 sm:p-8">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ config('app.name', 'School Book Manager') }}</h1>
            <p class="mt-1 text-slate-500">Student book invoice</p>
        </div>
        <div class="sm:text-right">
            <div class="text-xl font-semibold">{{ $invoice->invoice_number }}</div>
            <div class="muted">{{ $invoice->invoice_date->format('d M Y') }}</div>
        </div>
    </div>

    <div class="grid gap-4 py-6 sm:grid-cols-2">
        <div>
            <div class="stat-label">Billed to</div>
            <div class="mt-1 font-semibold">{{ $invoice->student->full_name }}</div>
            <div class="muted">{{ $invoice->student->student_number }}</div>
        </div>
        <div class="sm:text-right">
            <div class="stat-label">Class</div>
            <div class="mt-1 font-semibold">{{ $invoice->grade->name }}{{ $invoice->section ? '.'.$invoice->section->name : '' }}</div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table min-w-[600px]">
            <thead>
                <tr><th>Item</th><th>Quantity</th><th>Price</th><th>Total</th></tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->unit_price, 2) }}</td>
                        <td>{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="ml-auto mt-6 max-w-xs space-y-2">
        <div class="flex justify-between"><span class="muted">Total</span><strong>{{ number_format($invoice->total, 2) }}</strong></div>
        <div class="flex justify-between"><span class="muted">Paid</span><strong>{{ number_format($invoice->amount_paid, 2) }}</strong></div>
        <div class="flex justify-between border-t border-slate-200 pt-2"><span class="font-semibold">Balance due</span><strong>{{ number_format($invoice->balance_due, 2) }}</strong></div>
    </div>
</article>
@endsection
