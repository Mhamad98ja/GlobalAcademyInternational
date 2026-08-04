@extends('layouts.app', ['title' => 'Suppliers'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Suppliers</h1>
        <p class="page-subtitle">Supplier contacts, purchases and the amount currently due to each supplier.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('suppliers.create') }}">Add supplier</a>
</div>

<section class="panel">
    <div class="panel-header">
        <form class="flex w-full max-w-lg gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Supplier name, code or phone">
            <button class="btn btn-secondary">Search</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data-table min-w-[760px]">
            <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Opening balance</th><th>Balance due</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td><a class="link" href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a><div class="text-xs text-slate-500">{{ $supplier->code }}</div></td>
                        <td>{{ $supplier->contact_person ?: '—' }}</td>
                        <td>{{ $supplier->phone ?: '—' }}</td>
                        <td>{{ number_format((float) $supplier->opening_balance, 2) }}</td>
                        <td class="{{ $supplier->account_balance > 0 ? 'font-semibold text-amber-700' : 'text-emerald-700' }}">{{ number_format((float) $supplier->account_balance, 2) }}</td>
                        <td><span class="badge {{ $supplier->is_active ? 'badge-green' : 'badge-red' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No suppliers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-4 py-3">{{ $suppliers->links() }}</div>
</section>
@endsection
