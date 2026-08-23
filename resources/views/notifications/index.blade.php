@extends('layouts.app', ['title' => 'Notifications'])
@section('content')
<div class="page-header"><div><h1 class="page-title">Notifications</h1><p class="page-subtitle">Transfer dispatches, receipt decisions, and internal settlement updates.</p></div></div>
<section class="panel divide-y divide-slate-200">
@forelse($notifications as $notification)
    @php($data = $notification->data)
    <article class="p-5 {{ $notification->read_at ? '' : 'bg-sky-50/60' }}">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><div class="flex items-center gap-2"><h2 class="font-semibold text-slate-900">{{ $data['title'] ?? 'System notification' }}</h2>@if(! $notification->read_at)<span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">New</span>@endif</div><p class="mt-1 text-sm text-slate-600">{{ $data['message'] ?? '' }}</p><div class="mt-2 text-xs text-slate-500">{{ $notification->created_at->format('d M Y H:i') }} @if(!empty($data['invoice_number'])) · {{ $data['invoice_number'] }}@endif</div></div><div class="flex flex-wrap gap-2">@if(!empty($data['action_url']))<a class="btn btn-secondary" href="{{ $data['action_url'] }}">Open transfer</a>@endif @if(! $notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<input type="hidden" name="redirect_to" value="{{ route('notifications.index') }}"><button class="btn btn-secondary">Mark read</button></form>@endif</div></div>
    </article>
@empty
    <div class="empty">No notifications yet.</div>
@endforelse
</section>
<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
