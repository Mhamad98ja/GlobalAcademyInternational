@extends('layouts.app', ['title' => 'Messages'])

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Messages</h1><p class="page-subtitle">Internal school communication between staff members in the active branch.</p></div>
</div>

<div class="grid gap-5 xl:grid-cols-[1.35fr_.85fr]">
    <section class="panel">
        <div class="panel-header"><div><h2 class="panel-title">Inbox</h2><p class="mt-0.5 text-xs text-slate-500">Messages sent to you.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>From</th><th>Subject</th><th>Sent</th><th></th></tr></thead><tbody>
            @forelse ($inbox as $receipt)
                <tr class="{{ $receipt->read_at ? '' : 'font-semibold' }}">
                    <td>{{ $receipt->message?->sender?->name ?? 'Global Academy International' }}</td>
                    <td><div class="text-slate-900">{{ $receipt->message?->subject }}</div><div class="mt-1 max-w-lg whitespace-pre-line text-xs font-normal text-slate-500">{{ str($receipt->message?->body)->limit(160) }}</div></td>
                    <td class="font-normal">{{ $receipt->message?->sent_at?->format('d M Y H:i') }}</td>
                    <td>@if (! $receipt->read_at)<form method="POST" action="{{ route('messages.read', $receipt) }}">@csrf<button class="btn btn-secondary btn-small">Mark read</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">Your inbox is clear.</td></tr>
            @endforelse
        </tbody></table></div>
        @if ($inbox->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $inbox->links() }}</div>@endif
    </section>

    <section class="panel h-fit">
        <div class="panel-header"><div><h2 class="panel-title">Compose message</h2><p class="mt-0.5 text-xs text-slate-500">Select one or more colleagues in this branch.</p></div></div>
        <form method="POST" action="{{ route('messages.store') }}" class="space-y-4 p-5" data-submit-lock>
            @csrf
            <div><label class="form-label" for="recipient_ids">Recipients *</label><select id="recipient_ids" name="recipient_ids[]" multiple required size="8">@foreach ($recipients as $recipient)<option value="{{ $recipient->id }}">{{ $recipient->name }} — {{ $recipient->email }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Use Ctrl / Cmd to select more than one recipient.</p></div>
            <div><label class="form-label" for="subject">Subject *</label><input id="subject" name="subject" maxlength="180" required value="{{ old('subject') }}"></div>
            <div><label class="form-label" for="body">Message *</label><textarea id="body" name="body" rows="7" maxlength="10000" required>{{ old('body') }}</textarea></div>
            <div class="flex justify-end"><button class="btn btn-primary">Send message</button></div>
        </form>
    </section>
</div>

<section class="panel mt-5">
    <div class="panel-header"><div><h2 class="panel-title">Sent messages</h2><p class="mt-0.5 text-xs text-slate-500">Latest messages sent from your account.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Subject</th><th>Recipients</th><th>Sent</th></tr></thead><tbody>
        @forelse ($sent as $message)
            <tr><td><div class="font-medium text-slate-900">{{ $message->subject }}</div><div class="mt-1 max-w-xl whitespace-pre-line text-xs text-slate-500">{{ str($message->body)->limit(180) }}</div></td><td>{{ $message->recipients_count }}</td><td>{{ $message->sent_at->format('d M Y H:i') }}</td></tr>
        @empty
            <tr><td colspan="3" class="empty">You have not sent any messages yet.</td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection
