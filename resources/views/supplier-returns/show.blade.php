@extends('layouts.app', ['title' => 'Supplier return'])

@section('content')
<div class="page-header">
    <div>
        <div class="text-sm text-slate-500"><a class="link" href="{{ route('purchases.show', $supplierReturn->purchase) }}">{{ $supplierReturn->purchase->purchase_number }}</a> / Supplier return</div>
        <h1 class="page-title mt-1">Supplier return</h1>
        <p class="page-subtitle">{{ $supplierReturn->supplier->name }} &middot; {{ $supplierReturn->returned_on->format('d M Y') }}@if ($supplierReturn->reference_number) &middot; {{ $supplierReturn->reference_number }}@endif</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('purchases.show', $supplierReturn->purchase) }}">Back to purchase</a>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div class="stat"><div class="stat-label">Supplier credit</div><div class="stat-value text-emerald-700">{{ number_format((float) $supplierReturn->credit_total, 2) }}</div></div>
    <div class="stat"><div class="stat-label">Recorded by</div><div class="stat-value text-lg">{{ $supplierReturn->returnedBy?->full_name ?? '-' }}</div></div>
</div>

<section class="panel mt-6">
    <div class="panel-header"><h2 class="panel-title">Returned books</h2><span class="badge badge-green">{{ str($supplierReturn->status)->headline() }}</span></div>
    <div class="table-wrap">
        <table class="data-table min-w-[650px]">
            <thead><tr><th>Book</th><th>Quantity</th><th>Reason</th><th>Credit</th></tr></thead>
            <tbody>
                @foreach ($supplierReturn->items as $item)
                    <tr><td>{{ $item->book->title }}</td><td>{{ $item->quantity }}</td><td>{{ $item->reason }}</td><td class="text-emerald-700">{{ number_format((float) $item->credit_total, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($supplierReturn->notes)<div class="border-t border-slate-200 p-5"><div class="form-label">Notes</div><p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $supplierReturn->notes }}</p></div>@endif
</section>
@endsection
