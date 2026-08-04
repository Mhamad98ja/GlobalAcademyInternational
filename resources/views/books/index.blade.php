@extends('layouts.app', ['title' => 'Books'])

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Book catalogue</h1><p class="page-subtitle">Products, barcodes, purchase and sale prices, and stock position.</p></div>
    <a class="btn btn-primary" href="{{ route('books.create') }}">Add book</a>
</div>

<section class="panel">
    <div class="panel-header"><form class="flex w-full max-w-lg gap-2"><input name="search" value="{{ request('search') }}" placeholder="Title, ISBN, SKU or barcode"><button class="btn btn-secondary">Search</button></form></div>
    <div class="table-wrap">
        <table class="data-table min-w-[760px]">
            <thead><tr><th>Book</th><th>Barcode</th><th>Purchase price</th><th>Sale price</th><th>Available</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td><a class="link" href="{{ route('books.show', $book) }}">{{ $book->title }}</a><div class="text-xs text-slate-500">{{ $book->sku }}{{ $book->author ? ' · '.$book->author : '' }}</div></td>
                        <td class="font-mono text-xs">{{ $book->barcode }}</td>
                        <td>{{ number_format((float) $book->purchase_price, 2) }}</td>
                        <td class="font-semibold">{{ number_format((float) $book->selling_price, 2) }}</td>
                        <td><span class="badge {{ $book->available_quantity <= $book->minimum_stock ? 'badge-amber' : 'badge-green' }}">{{ $book->available_quantity }}</span></td>
                        <td><span class="badge {{ $book->is_active ? 'badge-green' : 'badge-red' }}">{{ $book->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No books found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-4 py-3">{{ $books->links() }}</div>
</section>
@endsection
