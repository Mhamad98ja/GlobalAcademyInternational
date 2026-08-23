@extends('layouts.app', ['title' => 'Financial Center'])

@section('content')
@php($number = static fn (?float $amount) => $amount === null ? '—' : number_format($amount, 2))

<div class="page-header">
    <div>
        <div class="text-xs font-semibold uppercase tracking-[.1em] text-teal-700">One financial workspace</div>
        <h1 class="page-title">Financial Center</h1>
        <p class="page-subtitle">Student sales, supplier purchases, payments, credits, refunds, and branch-transfer values in one place—kept financially separate.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($canViewSuppliers)<a class="btn btn-secondary" href="{{ route('purchases.create') }}">New supplier purchase</a>@endif
        @if (auth()->user()->hasPermission('distributions.create'))<a class="btn btn-primary" href="{{ route('distributions.create') }}">New student sale</a>@endif
        @if ($canViewTransfers)<a class="btn btn-secondary" href="{{ route('transfers.create') }}">New branch transfer</a>@endif
    </div>
</div>

<section class="mb-6 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-950">
    <span class="font-semibold">How the totals work:</span> student payments are cash in, supplier payments and refunds are cash out, and branch transfer invoices are internal stock values only. They are visible here but never counted as revenue or supplier expense.
</section>

<section class="panel mb-6">
    <div class="panel-header">
        <form method="GET" action="{{ route('finance.index') }}" class="grid w-full gap-2 md:grid-cols-[12rem_10rem_10rem_10rem_minmax(0,1fr)_auto_auto]">
            <select name="tab" aria-label="Financial area">
                <option value="all" @selected($tab === 'all')>All financial activity</option>
                @if ($canViewStudents)<option value="students" @selected($tab === 'students')>Student sales & payments</option>@endif
                @if ($canViewSuppliers)<option value="suppliers" @selected($tab === 'suppliers')>Supplier purchases & payments</option>@endif
                @if ($canViewTransfers)<option value="branches" @selected($tab === 'branches')>Branch transfer values</option>@endif
            </select>
            @if ($canViewAllBranches)
                <select name="scope" aria-label="Branch scope">
                    <option value="current" @selected(! $showAllBranches)>Current branch</option>
                    <option value="all" @selected($showAllBranches)>All branches</option>
                </select>
            @else
                <input type="hidden" name="scope" value="current">
            @endif
            <input type="date" name="date_from" value="{{ $dateFrom }}" aria-label="From date">
            <input type="date" name="date_to" value="{{ $dateTo }}" aria-label="To date">
            <input name="search" value="{{ $search }}" placeholder="Document number, student, supplier or branch" aria-label="Search financial activity">
            <button class="btn btn-secondary">Filter</button>
            <a class="btn btn-secondary" href="{{ route('finance.index') }}">Reset</a>
        </form>
    </div>
</section>

<div class="grid gap-5 xl:grid-cols-3">
    @if ($canViewStudents)
        <section class="panel overflow-hidden">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 bg-emerald-50 p-5">
                <div><div class="text-xs font-semibold uppercase tracking-[.1em] text-emerald-700">Students</div><h2 class="mt-1 text-lg font-semibold text-emerald-950">Sales & collections</h2></div>
                <a class="link text-xs font-semibold" href="{{ route('finance.index', ['tab' => 'students', 'scope' => $showAllBranches ? 'all' : 'current']) }}">Open ledger</a>
            </div>
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-100">
                @if ($canViewInvoices)<div class="p-4"><dt class="muted">Invoices issued</dt><dd class="mt-1 text-xl font-bold text-slate-900">{{ $number($studentSummary['invoiced']) }}</dd></div>@endif
                @if ($canViewPayments)<div class="p-4"><dt class="muted">Collected</dt><dd class="mt-1 text-xl font-bold text-emerald-700">{{ $number($studentSummary['collected']) }}</dd></div>@endif
                @if ($canViewInvoices)<div class="p-4"><dt class="muted">Outstanding</dt><dd class="mt-1 text-xl font-bold {{ $studentSummary['outstanding'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $number($studentSummary['outstanding']) }}</dd></div>@endif
                @if ($canViewReturns)<div class="p-4"><dt class="muted">Refunds / credits</dt><dd class="mt-1 text-xl font-bold text-rose-700">{{ $number($studentSummary['refunds']) }}</dd></div>@endif
            </dl>
            <div class="flex flex-wrap gap-3 border-t border-slate-100 px-5 py-3 text-xs">@if ($canViewInvoices)<a class="link" href="{{ route('invoices.index') }}">Student invoices</a>@endif @if ($canViewPayments)<a class="link" href="{{ route('payments.index') }}">Student receipts</a>@endif</div>
        </section>
    @endif

    @if ($canViewSuppliers)
        <section class="panel overflow-hidden">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 bg-blue-50 p-5">
                <div><div class="text-xs font-semibold uppercase tracking-[.1em] text-blue-700">Suppliers</div><h2 class="mt-1 text-lg font-semibold text-blue-950">Purchases & payables</h2></div>
                <a class="link text-xs font-semibold" href="{{ route('finance.index', ['tab' => 'suppliers', 'scope' => $showAllBranches ? 'all' : 'current']) }}">Open ledger</a>
            </div>
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-100">
                <div class="p-4"><dt class="muted">Purchase invoices</dt><dd class="mt-1 text-xl font-bold text-slate-900">{{ $number($supplierSummary['purchased']) }}</dd></div>
                <div class="p-4"><dt class="muted">Paid to suppliers</dt><dd class="mt-1 text-xl font-bold text-rose-700">{{ $number($supplierSummary['paid']) }}</dd></div>
                <div class="p-4"><dt class="muted">Supplier credits</dt><dd class="mt-1 text-xl font-bold text-emerald-700">{{ $number($supplierSummary['credits']) }}</dd></div>
                <div class="p-4"><dt class="muted">Outstanding payable</dt><dd class="mt-1 text-xl font-bold {{ $supplierSummary['outstanding'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $number($supplierSummary['outstanding']) }}</dd></div>
            </dl>
            <div class="border-t border-slate-100 px-5 py-3 text-xs"><a class="link" href="{{ route('purchases.index') }}">Supplier purchase documents</a></div>
        </section>
    @endif

    @if ($canViewTransfers)
        <section class="panel overflow-hidden">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 bg-amber-50 p-5">
                <div><div class="text-xs font-semibold uppercase tracking-[.1em] text-amber-700">Branches</div><h2 class="mt-1 text-lg font-semibold text-amber-950">Internal settlements</h2></div>
                <a class="link text-xs font-semibold" href="{{ route('finance.index', ['tab' => 'branches', 'scope' => $showAllBranches ? 'all' : 'current']) }}">Open ledger</a>
            </div>
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-100">
                <div class="p-4"><dt class="muted">Accepted documents</dt><dd class="mt-1 text-xl font-bold text-slate-900">{{ $number($branchSummary['accepted_documents']) }}</dd></div>
                <div class="p-4"><dt class="muted">Pending receipt</dt><dd class="mt-1 text-xl font-bold text-amber-700">{{ $number($branchSummary['pending_documents']) }}</dd></div>
                <div class="p-4"><dt class="muted">Settled amount received</dt><dd class="mt-1 text-xl font-bold text-emerald-700">{{ $number($branchSummary['cash_in']) }}</dd></div>
                <div class="p-4"><dt class="muted">Cash-control balance</dt><dd class="mt-1 text-xl font-bold {{ $branchSummary['cash_control_balance'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $number($branchSummary['cash_control_balance']) }}</dd></div>
            </dl>
            <div class="border-t border-slate-100 px-5 py-3 text-xs"><a class="link" href="{{ route('transfers.index', $showAllBranches ? ['all_branches' => 1] : []) }}">Transfer documents</a> <span class="ml-2 text-slate-500">Not included in cash or profit totals.</span></div>
        </section>
    @endif
</div>

<section class="panel mt-6">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Financial activity ledger</h2>
            <p class="mt-1 text-xs text-slate-500">Every entry retains its own document number and detail page. Invoice values are not cash until a payment is recorded.</p>
        </div>
        <span class="text-xs font-medium text-slate-500">{{ $showAllBranches ? 'All branches' : 'Current branch' }}</span>
    </div>
    <div class="table-wrap">
        <table class="data-table min-w-[1150px]">
            <thead><tr><th>Area</th><th>Document / number</th><th>Party / branches</th><th>Branch</th><th>Date</th><th>Document value</th><th>Cash in</th><th>Cash out</th><th>Balance</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    @php($badge = $transaction['category'] === 'students' ? 'badge-green' : ($transaction['category'] === 'suppliers' ? 'badge-blue' : 'badge-amber'))
                    @php($statusClass = in_array($transaction['status'], ['cancelled', 'reversed', 'voided'], true) ? 'badge-red' : (in_array($transaction['status'], ['paid', 'valid', 'accepted', 'completed', 'settled', 'cash', 'card', 'bank_transfer', 'check'], true) ? 'badge-green' : 'badge-amber'))
                    <tr>
                        <td><span class="badge {{ $badge }}">{{ str($transaction['category'])->headline() }}</span><div class="mt-1 text-xs text-slate-500">{{ $transaction['kind'] }}</div></td>
                        <td><a class="link font-medium" href="{{ $transaction['href'] }}">{{ $transaction['reference'] }}</a><div class="text-xs text-slate-500">{{ $transaction['detail'] }}</div></td>
                        <td>{{ $transaction['party'] }}</td>
                        <td>{{ $transaction['branch'] ?: '—' }}</td>
                        <td>{{ $transaction['date']->format('d M Y') }}</td>
                        <td>{{ number_format($transaction['value'], 2) }}</td>
                        <td class="text-emerald-700">{{ $transaction['cashIn'] > 0 ? number_format($transaction['cashIn'], 2) : '—' }}</td>
                        <td class="text-rose-700">{{ $transaction['cashOut'] > 0 ? number_format($transaction['cashOut'], 2) : '—' }}</td>
                        <td class="{{ $transaction['balance'] !== null && $transaction['balance'] > 0 ? 'text-amber-700' : 'text-slate-600' }}">{{ $transaction['balance'] === null ? '—' : number_format($transaction['balance'], 2) }}</td>
                        <td><span class="badge {{ $statusClass }}">{{ str($transaction['status'])->headline() }}</span></td>
                        <td><a class="link text-xs" href="{{ $transaction['href'] }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="empty">No financial activity matches these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-4 py-3">{{ $transactions->links() }}</div>
</section>
@endsection
