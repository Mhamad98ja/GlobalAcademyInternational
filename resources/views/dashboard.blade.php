@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
<div class="page-header">
    <div>
        <div class="text-xs font-semibold uppercase tracking-[.1em] text-teal-700">Overview</div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Your school book operations at a glance.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($activeAcademicYear)
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><span class="font-semibold">Active academic year:</span> {{ $activeAcademicYear->name }}</div>
        @else
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">No academic year is Open. Set one to Open in Academic Structure.</div>
        @endif
        <a class="btn btn-primary" href="{{ route('distributions.create') }}">New distribution</a>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 {{ $canViewFinancials ? 'xl:grid-cols-4' : 'xl:grid-cols-2' }}">
    <div class="stat"><div class="stat-label">Active students</div><div class="stat-value">{{ number_format($activeStudents) }}</div><div class="stat-note">{{ $activeAcademicYear ? 'In '.$activeAcademicYear->name : 'Set an open academic year' }}</div></div>
    <div class="stat"><div class="stat-label">Books in stock</div><div class="stat-value">{{ number_format($stock) }}</div><div class="stat-note">Across active titles</div></div>
    @if ($canViewFinancials)
        <div class="stat"><div class="stat-label">Today's collection</div><div class="stat-value">{{ number_format($collected, 2) }}</div><div class="stat-note">Valid payments for the active year</div></div>
        <div class="stat"><div class="stat-label">Outstanding balance</div><div class="stat-value">{{ number_format($outstanding, 2) }}</div><div class="stat-note">{{ $overdueDeferred }} overdue deferred approvals</div></div>
    @endif
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.45fr_1fr]">
    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Low stock</h2><p class="mt-0.5 text-xs text-slate-500">Titles at or below their reorder threshold</p></div><a class="link text-sm" href="{{ route('books.index') }}">View catalogue</a></div>
        <div class="table-wrap">
            <table class="data-table"><thead><tr><th>Book</th><th>Shelf</th><th>On hand</th><th>Minimum</th></tr></thead>
                <tbody>@forelse ($lowStock as $book)<tr><td><a class="link" href="{{ route('books.show', $book) }}">{{ $book->title }}</a><div class="text-xs text-slate-500">{{ $book->sku }}</div></td><td>{{ $book->shelf_location ?: '-' }}</td><td><span class="badge {{ $book->stock_on_hand === 0 ? 'badge-red' : 'badge-amber' }}">{{ $book->stock_on_hand }}</span></td><td>{{ $book->minimum_stock }}</td></tr>@empty<tr><td colspan="4" class="empty">No low-stock titles. Good work.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Today</h2><p class="mt-0.5 text-xs text-slate-500">Active-year operational summary</p></div></div>
        <dl class="divide-y divide-slate-100 px-5"><div class="flex items-center justify-between py-4"><dt class="text-slate-600">Completed distributions</dt><dd class="font-semibold text-slate-900">{{ $todayDistributions }}</dd></div><div class="flex items-center justify-between py-4"><dt class="text-slate-600">New stock movements</dt><dd class="font-semibold text-slate-900">{{ $recentActivity->count() }}</dd></div>@if ($canViewFinancials)<div class="flex items-center justify-between py-4"><dt class="text-slate-600">Overdue approvals</dt><dd class="font-semibold {{ $overdueDeferred ? 'text-rose-700' : 'text-emerald-700' }}">{{ $overdueDeferred }}</dd></div>@endif</dl>
    </section>
</div>

<section class="panel mt-6">
    <div class="panel-header"><div><h2 class="panel-title">Recent inventory activity</h2><p class="mt-0.5 text-xs text-slate-500">Controlled movements posted in the active academic year</p></div><a class="link text-sm" href="{{ route('inventory.ledger') }}">Open ledger</a></div>
    <div class="table-wrap">
        <table class="data-table"><thead><tr><th>When</th><th>Book</th><th>Movement</th><th>Change</th><th>By</th></tr></thead>
            <tbody>@forelse ($recentActivity as $movement)<tr><td>{{ $movement->created_at->format('d M, H:i') }}</td><td>{{ $movement->book->title }}</td><td class="capitalize">{{ str_replace('_', ' ', $movement->type) }}</td><td class="font-semibold {{ $movement->quantity_change < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}</td><td>{{ $movement->user?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="5" class="empty">{{ $activeAcademicYear ? 'No movements recorded for the active academic year.' : 'Set an open academic year to see its activity.' }}</td></tr>@endforelse</tbody>
        </table>
    </div>
</section>
@endsection
