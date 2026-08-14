@extends('layouts.app', ['title' => 'Books & packages'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Books &amp; packages</h1>
        <p class="page-subtitle">{{ $tab === 'packages' ? 'Required and optional book packages by academic year, grade, and section.' : 'Products, barcodes, purchase and sale prices, and stock position.' }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($canManageBooks)<a class="btn btn-secondary" href="{{ route('books.create') }}">Add book</a>@endif
        @if ($canManagePackages)<a class="btn btn-primary" href="{{ route('class-lists.create') }}">New package</a>@endif
    </div>
</div>

@if ($canManagePackages)
    <nav class="mb-5 flex w-fit gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm" aria-label="Books and packages">
        <a class="rounded-md px-3 py-2 text-sm font-semibold {{ $tab === 'books' ? 'bg-teal-700 text-white' : 'text-slate-600 hover:bg-slate-100' }}" href="{{ route('books.index') }}">Books</a>
        <a class="rounded-md px-3 py-2 text-sm font-semibold {{ $tab === 'packages' ? 'bg-teal-700 text-white' : 'text-slate-600 hover:bg-slate-100' }}" href="{{ route('books.index', ['tab' => 'packages']) }}">Book packages</a>
    </nav>
@endif

<section class="panel">
    <div class="panel-header">
        <form class="flex w-full max-w-lg gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input name="search" value="{{ $search }}" placeholder="{{ $tab === 'packages' ? 'Package, academic year, grade or section' : 'Title, ISBN, SKU or barcode' }}">
            <button class="btn btn-secondary">Search</button>
        </form>
    </div>

    @if ($tab === 'packages')
        <div class="table-wrap">
            <table class="data-table min-w-[760px]">
                <thead><tr><th>Package</th><th>Academic year</th><th>Grade</th><th>Section</th><th>Books</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($lists as $list)
                        <tr>
                            <td class="font-medium">{{ $list->name }}</td>
                            <td>{{ $list->academicYear->name }}</td>
                            <td>{{ $list->grade->name }}</td>
                            <td>{{ $list->section?->name ?? 'All sections' }}</td>
                            <td>{{ $list->items->count() }}</td>
                            <td><span class="badge {{ $list->is_active ? 'badge-green' : 'badge-red' }}">{{ $list->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td><a class="link text-xs" href="{{ route('class-lists.edit', $list) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No class book packages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $lists->links() }}</div>
    @else
        <div class="table-wrap">
            <table class="data-table min-w-[760px]">
                <thead><tr><th>Book</th><th>Usage</th><th>Barcode</th><th>Purchase price</th><th>Sale price</th><th>Available</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($books as $book)
                        <tr>
                            <td><a class="link" href="{{ route('books.show', $book) }}">{{ $book->title }}</a><div class="text-xs text-slate-500">{{ $book->sku }}{{ $book->author ? ' &middot; '.$book->author : '' }}</div></td>
                            <td>{{ $book->gradeUsages->pluck('audience')->map(fn ($audience) => ucfirst($audience))->unique()->implode(', ') ?: 'â€”' }}</td>
                            <td class="font-mono text-xs">{{ $book->barcode }}</td>
                            <td>{{ number_format((float) $book->purchase_price, 2) }}</td>
                            <td class="font-semibold">{{ number_format((float) $book->selling_price, 2) }}</td>
                            <td><span class="badge {{ $book->available_quantity <= $book->minimum_stock ? 'badge-amber' : 'badge-green' }}">{{ $book->available_quantity }}</span></td>
                            <td><span class="badge {{ $book->is_active ? 'badge-green' : 'badge-red' }}">{{ $book->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No books found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $books->links() }}</div>
    @endif
</section>
@endsection
