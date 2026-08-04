@extends('layouts.app', ['title' => 'Return books to supplier'])

@section('content')
<div class="page-header">
    <div>
        <div class="text-sm text-slate-500"><a class="link" href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a> / Supplier return</div>
        <h1 class="page-title mt-1">Return books to supplier</h1>
        <p class="page-subtitle">{{ $purchase->supplier->name }}@if ($purchase->supplier_invoice_number) &middot; Supplier invoice {{ $purchase->supplier_invoice_number }}@endif</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('purchases.show', $purchase) }}">Cancel</a>
</div>

<section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
    Select only the books being sent back. Completing this return reduces available stock and creates a credit against this supplier purchase.
</section>

<form method="POST" action="{{ route('supplier-returns.store', $purchase) }}" class="panel" data-submit-lock>
    @csrf
    <div class="grid gap-4 border-b border-slate-200 p-5 sm:grid-cols-2">
        <div><label class="form-label">Return date</label><input type="date" name="returned_on" value="{{ old('returned_on', today()->format('Y-m-d')) }}" required></div>
        <div><label class="form-label">Supplier credit / reference number</label><input name="reference_number" value="{{ old('reference_number') }}" placeholder="Optional credit note number"></div>
    </div>
    <div class="table-wrap">
        <table class="data-table min-w-[800px]">
            <thead><tr><th>Book</th><th>Received</th><th>Available to return</th><th>Return quantity</th><th>Reason</th></tr></thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    <tr>
                        <td>
                            {{ $item->book->title }}
                            <input type="hidden" name="items[{{ $loop->index }}][supplier_purchase_item_id]" value="{{ $item->id }}">
                        </td>
                        <td>{{ $item->quantity_received }}</td>
                        <td>{{ $item->returnable_quantity }}</td>
                        <td><input type="number" name="items[{{ $loop->index }}][quantity]" min="0" max="{{ $item->returnable_quantity }}" value="{{ old('items.'.$loop->index.'.quantity', 0) }}" @disabled($item->returnable_quantity === 0) required></td>
                        <td><input name="items[{{ $loop->index }}][reason]" value="{{ old('items.'.$loop->index.'.reason') }}" placeholder="Required when a quantity is entered" @disabled($item->returnable_quantity === 0)></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-5"><label class="form-label">Notes</label><textarea name="notes" rows="3" placeholder="Optional internal notes">{{ old('notes') }}</textarea></div>
    <div class="flex justify-end border-t border-slate-200 p-5"><button class="btn btn-primary">Complete supplier return</button></div>
</form>
@endsection
