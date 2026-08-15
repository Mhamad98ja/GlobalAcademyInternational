@extends('layouts.app', ['title' => $book->title])

@section('content')
<div class="page-header">
    <div><div class="text-sm text-slate-500"><a class="link" href="{{ route('books.index') }}">Books</a> / Detail</div><h1 class="page-title mt-1">{{ $book->title }}</h1><p class="page-subtitle">{{ $book->sku }} · Barcode {{ $book->barcode }}</p></div>
    <div class="flex gap-2">@if (auth()->user()->hasPermission('books.manage'))<a class="btn btn-secondary" href="{{ route('books.edit', $book) }}">Edit</a>@endif @if (auth()->user()->hasPermission('inventory.adjust'))<a class="btn btn-primary" href="{{ route('inventory.adjustments') }}">Adjust stock</a>@endif</div>
</div>

<div class="grid gap-4 sm:grid-cols-3"><div class="stat"><div class="stat-label">On hand (current branch)</div><div class="stat-value">{{ $book->branch_quantity }}</div></div><div class="stat"><div class="stat-label">Reserved</div><div class="stat-value">{{ $book->branchStocks->first()?->reserved_quantity ?? $book->reserved_quantity }}</div></div><div class="stat"><div class="stat-label">Available</div><div class="stat-value">{{ $book->available_quantity }}</div></div></div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1.5fr]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Catalogue details</h2></div>
        <dl class="divide-y divide-slate-100 px-5">
            @php($details = ['ISBN' => $book->isbn, 'Author' => $book->author, 'Publisher' => $book->publisher, 'Subject' => $book->subject, 'Format' => $book->media_format ? str_replace('_', ' ', $book->media_format) : null, 'Used by' => match($book->default_audience) { 'student' => 'Student', 'teacher' => 'Teacher', 'both' => 'Students and teachers', default => null }, 'Shelf' => $book->shelf_location])
            @if ($canViewFinancials) @php($details = [...$details, 'Latest purchase price' => number_format((float) $book->purchase_price, 2), 'Selling price' => number_format((float) $book->selling_price, 2)]) @endif
            @foreach ($details as $label => $value)
                <div class="flex justify-between gap-4 py-3"><dt class="muted">{{ $label }}</dt><dd class="text-right font-medium text-slate-800">{{ $value ?: '—' }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Recent stock movements</h2><a class="link text-sm" href="{{ route('inventory.ledger', ['book_id' => $book->id]) }}">Full ledger</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>When</th><th>Type</th><th>Change</th><th>After</th></tr></thead><tbody>@forelse ($book->movements as $movement)<tr><td>{{ $movement->created_at->format('d M Y H:i') }}</td><td class="capitalize">{{ str_replace('_', ' ', $movement->type) }}</td><td class="font-semibold {{ $movement->quantity_change < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}</td><td>{{ $movement->quantity_after }}</td></tr>@empty<tr><td colspan="4" class="empty">No movement recorded.</td></tr>@endforelse</tbody></table></div>
    </section>
</div>
<section class="panel mt-6"><div class="panel-header"><h2 class="panel-title">Usage by grade</h2></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Grade</th><th>Used by</th></tr></thead><tbody>@forelse($book->gradeUsages->sortBy('grade.sort_order') as $usage)<tr><td>{{ $usage->grade->name }}</td><td class="capitalize">{{ $usage->audience }}</td></tr>@empty<tr><td colspan="2" class="empty">No grade usage has been recorded.</td></tr>@endforelse</tbody></table></div></section>
@endsection
