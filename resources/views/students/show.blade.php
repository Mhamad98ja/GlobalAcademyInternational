@extends('layouts.app', ['title' => $student->full_name])

@section('content')
@php($hasActiveRegistration = $student->registrations->contains(fn ($registration) => $registration->status === 'active'))
<div class="page-header">
    <div>
        <div class="text-sm text-slate-500"><a class="link" href="{{ route('students.index') }}">Students</a> / Profile</div>
        <h1 class="page-title mt-1">{{ $student->full_name }}</h1>
        <p class="page-subtitle">{{ $student->student_number }} · {{ $student->phone ?: 'No phone recorded' }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="{{ route('students.edit', $student) }}">Edit profile</a>
        @if ($hasActiveRegistration)
            <a class="btn btn-secondary" href="{{ route('students.leave.create', $student) }}">Mark as left school</a>
        @endif
        <a class="btn btn-primary" href="{{ route('students.register.create', $student) }}">New registration</a>
        <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Move this student profile to the recycle bin? It can be restored by an administrator.');">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger">Delete profile</button>
        </form>
    </div>
</div>

<div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Academic registrations</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Academic year</th><th>Class</th><th>Teacher</th><th>Status</th><th>Leaving record</th><th></th></tr></thead>
                <tbody>
                    @forelse ($student->registrations as $registration)
                        <tr>
                            <td>{{ $registration->academicYear->name }}</td>
                            <td>{{ $registration->grade->name }}{{ $registration->section ? ' · '.$registration->section->name : '' }}</td>
                            <td>{{ $registration->teacher?->full_name ?? '—' }}</td>
                            <td><span class="badge {{ $registration->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ str($registration->status)->headline() }}</span></td>
                            <td>
                                @if ($registration->withdrawn_on)
                                    <div>{{ $registration->withdrawn_on->format('d M Y') }}</div>
                                    @if ($registration->withdrawal_reason)<div class="mt-1 text-xs text-slate-500">{{ $registration->withdrawal_reason }}</div>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="space-x-3">
                                <a class="link text-xs" href="{{ route('students.register.edit', [$student, $registration]) }}">Edit</a>
                                @if ($registration->status === 'active')
                                    <a class="link text-xs" href="{{ route('distributions.create', ['registration' => $registration->id]) }}">Distribute books</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No academic-year registration yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Responsible persons</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse ($student->responsiblePersons as $person)
                <div class="px-5 py-3">
                    <div class="font-medium text-slate-800">{{ $person->full_name }} @if ($person->pivot->is_primary)<span class="badge badge-blue">Primary</span>@endif</div>
                    <div class="text-xs text-slate-500">{{ ucfirst($person->type) }} · {{ $person->phone ?: 'No phone' }}</div>
                </div>
            @empty
                <div class="empty">No responsible person linked.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="panel mt-6">
    <div class="panel-header"><h2 class="panel-title">Invoices</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($student->invoices as $invoice)
                    <tr>
                        <td><a class="link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                        <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                        <td>{{ number_format($invoice->total, 2) }}</td>
                        <td>{{ number_format($invoice->amount_paid, 2) }}</td>
                        <td>{{ number_format($invoice->balance_due, 2) }}</td>
                        <td><span class="badge {{ $invoice->payment_status === 'paid' ? 'badge-green' : 'badge-amber' }}">{{ str_replace('_', ' ', $invoice->payment_status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
