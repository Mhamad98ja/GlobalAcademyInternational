@extends('layouts.app', ['title' => 'Deferred-payment approvals'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Deferred-payment approvals</h1>
        <p class="page-subtitle">Requests are created during book distribution. The full unpaid invoice balance becomes deferred only after the branch administrator accepts it.</p>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Approval queue and payment follow-up</h2>
            <p class="mt-1 text-xs text-slate-500">For accepted requests, the remaining balance below comes directly from the invoice and updates after every payment.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table min-w-[1100px]">
            <thead>
                <tr>
                    <th>Student / invoice</th>
                    <th>Requested</th>
                    <th>Remaining balance</th>
                    <th>Decision</th>
                    <th>Payment follow-up</th>
                    <th>Requested by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($approvals as $approval)
                    @php($remaining = (float) ($approval->invoice?->balance_due ?? 0))
                    @php($decisionLabel = $approval->status === 'approved' ? 'Accepted' : str($approval->status)->headline())
                    @php($decisionClass = $approval->status === 'approved' ? 'badge-green' : ($approval->status === 'rejected' ? 'badge-red' : 'badge-amber'))
                    <tr>
                        <td>
                            <div class="font-medium">{{ $approval->student?->full_name }}</div>
                            <div class="text-xs text-slate-500">{{ $approval->invoice?->invoice_number }}</div>
                        </td>
                        <td>{{ number_format($approval->amount_deferred, 2) }}</td>
                        <td class="font-semibold {{ $remaining > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($remaining, 2) }}</td>
                        <td>
                            <span class="badge {{ $decisionClass }}">{{ $decisionLabel }}</span>
                            @if($approval->approval_date)
                                <div class="mt-1 text-xs text-slate-500">{{ $approval->approval_date->format('d M Y') }} &middot; {{ $approval->administrator?->name ?? 'Administrator' }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $approval->follow_up_status === 'settled' ? 'badge-green' : ($approval->status === 'rejected' ? 'badge-red' : 'badge-blue') }}">{{ str($approval->follow_up_status)->headline() }}</span>
                            @if($approval->status === 'rejected')
                                <div class="mt-1 text-xs text-rose-700">Payment required</div>
                            @elseif($approval->status === 'approved' && $remaining > 0)
                                <div class="mt-1 text-xs text-slate-500">Each payment reduces this balance.</div>
                            @endif
                        </td>
                        <td>{{ $approval->createdBy?->name ?? 'System' }}</td>
                        <td class="min-w-64">
                            @if($approval->status === 'pending' && $canApprove)
                                <form method="POST" action="{{ route('deferred.approve', $approval) }}" class="space-y-2">
                                    @csrf
                                    <textarea class="input text-xs" name="decision_notes" rows="2" placeholder="Decision note (required for rejection)"></textarea>
                                    <div class="flex gap-2">
                                        <button class="btn btn-small btn-primary" name="decision" value="approved">Accept</button>
                                        <button class="btn btn-small btn-danger" name="decision" value="rejected">Reject</button>
                                    </div>
                                </form>
                            @endif

                            @if($approval->decision_notes)
                                <div class="mt-2 text-xs text-slate-600"><span class="font-semibold">Decision note:</span> {{ $approval->decision_notes }}</div>
                            @endif

                            @if($approval->document_path)
                                <a class="link mt-2 inline-block text-xs" href="{{ route('deferred.document', $approval) }}">Authorization document</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No deferred-payment requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-200 p-3">{{ $approvals->links() }}</div>
</section>
@endsection
