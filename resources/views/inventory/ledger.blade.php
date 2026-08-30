@extends('layouts.app', ['title' => 'Inventory ledger'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Inventory ledger</h1>
        <p class="page-subtitle">Every stock change has a branch balance, source, responsible user, and full audit trail.</p>
    </div>
    <div class="flex gap-2">
        <a class="btn btn-secondary" href="{{ route('transfers.index') }}">Transfers</a>
        <a class="btn btn-primary" href="{{ route('inventory.adjustments') }}">New adjustment</a>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="flex w-full max-w-xl gap-2">
            <input type="search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search barcode, ISBN, or book name" aria-label="Search inventory ledger">
            <button class="btn btn-secondary">Search</button>
            @if (request()->filled('search'))<a class="btn btn-secondary" href="{{ route('inventory.ledger') }}">Reset</a>@endif
        </form>
    </div>
    <div class="table-wrap">
        <table class="data-table min-w-[1100px]">
            <thead><tr><th>Timestamp</th><th>Book</th><th>Type</th><th>From</th><th>To</th><th>Before</th><th>Change</th><th>After</th><th>Recorded by</th><th>Holder</th><th>Reason</th></tr></thead>
            <tbody>
                @forelse ($movements as $movement)
                    @php($transferEndpoints = $movement->transferEndpoints())
                    <tr>
                        <td>{{ $movement->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $movement->book->title }}</td>
                        <td class="capitalize">{{ str_replace('_', ' ', $movement->type) }}</td>
                        <td>{{ $transferEndpoints['from'] ?? '-' }}</td>
                        <td>{{ $transferEndpoints['to'] ?? '-' }}</td>
                        <td>{{ $movement->quantity_before }}</td>
                        <td class="font-semibold {{ $movement->quantity_change < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}</td>
                        <td>{{ $movement->quantity_after }}</td>
                        <td>{{ $movement->user?->name ?? 'System' }}</td>
                        <td>{{ $movement->holder?->name ?? '-' }}</td>
                        <td>{{ $movement->reason ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="empty">No inventory movements found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-4 py-3"><x-pagination :paginator="$movements" /></div>
</section>
@endsection
