@extends('layouts.app', ['title' => 'New supplier purchase'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">New supplier purchase</h1>
        <p class="page-subtitle">Saving a purchase immediately adds the quantities to stock and updates the book’s latest purchase price. Selling prices are not changed here.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('purchases.index') }}">Cancel</a>
</div>

<form method="POST" action="{{ route('purchases.store') }}" class="panel" data-submit-lock data-supplier-purchase-form>
    @csrf
    <div class="space-y-6 p-5">
        <div class="form-grid">
            <div>
                <label class="form-label">Supplier *</label>
                <select name="supplier_id" required>
                    <option value="">Select supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Academic year</label>
                <select name="academic_year_id">
                    <option value="">None</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Purchase date *</label>
                <input type="date" name="purchase_date" value="{{ old('purchase_date', today()->format('Y-m-d')) }}" required>
            </div>
            <div>
                <label class="form-label">Supplier invoice number</label>
                <input name="supplier_invoice_number" value="{{ old('supplier_invoice_number') }}">
                <p class="mt-1 text-xs text-slate-500">Use the number on the supplier's invoice. Payment or cheque numbers are recorded after saving.</p>
            </div>
        </div>

        <div>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="panel-title">Purchase items</h2>
                    <p class="mt-1 text-xs text-slate-500">Sale price is shown for reference. Only the supplier purchase price is saved on this purchase and updates the catalogue cost.</p>
                </div>
                <button type="button" class="btn btn-secondary btn-small" data-add-row="#purchase-lines" data-template="#purchase-line-template">Add line</button>
            </div>
            <div class="table-wrap">
                <table class="data-table min-w-[900px]">
                    <thead>
                        <tr><th>Book</th><th>Quantity</th><th>Unit purchase price</th><th>Catalogue sale price</th><th>Discount %</th><th>Tax %</th><th></th></tr>
                    </thead>
                    <tbody id="purchase-lines">
                        <tr data-line>
                            <td>
                                <select name="items[0][book_id]" required data-purchase-book>
                                    <option value="">Select book</option>
                                    @foreach ($books as $book)
                                        <option value="{{ $book->id }}" data-purchase-price="{{ $book->purchase_price }}" data-selling-price="{{ $book->selling_price }}">{{ $book->title }} (Sale: {{ number_format((float) $book->selling_price, 2) }})</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" min="1" name="items[0][quantity_ordered]" value="1" required></td>
                            <td><input type="number" min="0" step="0.01" name="items[0][unit_purchase_price]" required data-unit-purchase-price></td>
                            <td><span class="font-medium text-slate-700" data-catalogue-sale-price>—</span></td>
                            <td><input type="number" min="0" max="100" step="0.01" name="items[0][discount_percentage]" value="0"></td>
                            <td><input type="number" min="0" max="100" step="0.01" name="items[0][tax_rate]" value="0"></td>
                            <td><button type="button" class="link text-xs text-rose-700" data-remove-row>Remove</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>
    </div>
    <div class="flex justify-end border-t border-slate-200 p-5"><button class="btn btn-primary">Save purchase and receive stock</button></div>
</form>

<template id="purchase-line-template">
    <tr data-line>
        <td>
            <select name="items[__INDEX__][book_id]" required data-purchase-book>
                <option value="">Select book</option>
                @foreach ($books as $book)
                    <option value="{{ $book->id }}" data-purchase-price="{{ $book->purchase_price }}" data-selling-price="{{ $book->selling_price }}">{{ $book->title }} (Sale: {{ number_format((float) $book->selling_price, 2) }})</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" min="1" name="items[__INDEX__][quantity_ordered]" value="1" required></td>
        <td><input type="number" min="0" step="0.01" name="items[__INDEX__][unit_purchase_price]" required data-unit-purchase-price></td>
        <td><span class="font-medium text-slate-700" data-catalogue-sale-price>—</span></td>
        <td><input type="number" min="0" max="100" step="0.01" name="items[__INDEX__][discount_percentage]" value="0"></td>
        <td><input type="number" min="0" max="100" step="0.01" name="items[__INDEX__][tax_rate]" value="0"></td>
        <td><button type="button" class="link text-xs text-rose-700" data-remove-row>Remove</button></td>
    </tr>
</template>
@endsection
