@extends('layouts.app', ['title' => $book->exists ? 'Edit book' : 'Add book'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $book->exists ? 'Edit book' : 'Add book' }}</h1>
        <p class="page-subtitle">Set the stock quantity for the current branch; the change is recorded in the inventory ledger.</p>
    </div>
    <a class="btn btn-secondary" href="{{ $book->exists ? route('books.show', $book) : route('books.index') }}">Cancel</a>
</div>

<form class="panel" method="POST" action="{{ $book->exists ? route('books.update', $book) : route('books.store') }}" data-submit-lock>
    @csrf
    @if ($book->exists)
        @method('PUT')
    @endif
    <div class="space-y-6 p-5">
        <div>
            <h2 class="panel-title">Identification</h2>
            <div class="form-grid mt-4">
                <div><label class="form-label">SKU *</label><input name="sku" required value="{{ old('sku', $book->sku) }}"></div>
                <div><label class="form-label">Barcode *</label><input name="barcode" required value="{{ old('barcode', $book->barcode) }}"></div>
                <div><label class="form-label">ISBN</label><input name="isbn" value="{{ old('isbn', $book->isbn) }}"></div>
                <div><label class="form-label">Title *</label><input name="title" required value="{{ old('title', $book->title) }}"></div>
                <div><label class="form-label">Subtitle</label><input name="subtitle" value="{{ old('subtitle', $book->subtitle) }}"></div>
                <div><label class="form-label">Author</label><input name="author" value="{{ old('author', $book->author) }}"></div>
                <div><label class="form-label">Publisher</label><input name="publisher" value="{{ old('publisher', $book->publisher) }}"></div>
                <div><label class="form-label">Edition</label><input name="edition" value="{{ old('edition', $book->edition) }}"></div>
                <div><label class="form-label">Subject</label><input name="subject" value="{{ old('subject', $book->subject) }}"></div>
                <div><label class="form-label">Language</label><input name="language" value="{{ old('language', $book->language) }}"></div>
                <div><label class="form-label">Category</label><input name="category" value="{{ old('category', $book->category) }}"></div>
                <div><label class="form-label">Format</label><select name="media_format"><option value="">Not specified</option>@foreach(['print' => 'Print', 'digital' => 'Digital', 'print_and_digital' => 'Print + digital'] as $value => $label)<option value="{{ $value }}" @selected(old('media_format', $book->media_format) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="form-label">Used by</label><select name="default_audience"><option value="">Not specified</option>@foreach(['student' => 'Student', 'teacher' => 'Teacher', 'both' => 'Students and teachers'] as $value => $label)<option value="{{ $value }}" @selected(old('default_audience', $book->default_audience) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="form-label">Shelf location</label><input name="shelf_location" value="{{ old('shelf_location', $book->shelf_location) }}"></div>
            </div>
        </div>

        <div>
            <h2 class="panel-title">Pricing & availability</h2>
            <div class="form-grid mt-4">
                <div>
                    <label class="form-label">Latest purchase price</label>
                    <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ number_format((float) ($book->purchase_price ?? 0), 2) }}</div>
                    <p class="mt-1 text-xs text-slate-500">Set automatically from a supplier purchase.</p>
                </div>
                <div>
                    <label class="form-label">Selling price *</label>
                    <input type="number" step="0.01" min="0" name="selling_price" required value="{{ old('selling_price', $book->selling_price ?? '0.00') }}">
                    <p class="mt-1 text-xs text-slate-500">Used for manual distributions and as the default paid package price.</p>
                </div>
                <div><label class="form-label">Tax rate (%)</label><input type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', $book->tax_rate ?? '0.00') }}"></div>
                <div><label class="form-label">Minimum stock</label><input type="number" min="0" name="minimum_stock" value="{{ old('minimum_stock', $book->minimum_stock ?? 0) }}"></div>
                <div><label class="form-label">Stock quantity *</label><input type="number" min="0" max="1000000" name="stock_quantity" required value="{{ old('stock_quantity', $book->branch_quantity) }}"><p class="mt-1 text-xs text-slate-500">On-hand quantity in the active branch. Saving records any change as an adjustment.</p></div>
                <div class="md:col-span-2"><label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $book->exists ? $book->is_active : true))>Active in catalogue</label></div>
                <div class="md:col-span-2"><label class="form-label">Notes</label><textarea name="notes" rows="3">{{ old('notes', $book->notes) }}</textarea></div>
            </div>
        </div>
    </div>
    <div class="flex justify-end gap-3 border-t border-slate-200 p-5"><a class="btn btn-secondary" href="{{ route('books.index') }}">Cancel</a><button class="btn btn-primary">Save book</button></div>
</form>
@endsection
