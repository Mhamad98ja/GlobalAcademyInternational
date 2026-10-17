@extends('layouts.app', ['title' => 'Families without students'])

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Families without students</h1><p class="page-subtitle">Review unlinked parent and guardian records before they are attached to a student, corrected, or removed.</p></div>
    <a class="btn btn-primary" href="{{ route('responsible.index') }}">New parent / guardian</a>
</div>

<section class="panel">
    <div class="panel-header"><form method="GET" class="flex w-full max-w-xl gap-2"><input name="search" value="{{ request('search') }}" placeholder="Parent code, name, or phone"><button class="btn btn-secondary">Search</button><a class="btn btn-secondary" href="{{ route('students.families') }}">Reset</a></form></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Parent code</th><th>Contact</th><th>Type</th><th>Phone</th><th>Created</th><th></th></tr></thead><tbody>
        @forelse($families as $family)
            <tr><td>{{ $family->parent_code ?: '—' }}</td><td><div class="font-medium">{{ $family->full_name }}</div><div class="text-xs text-slate-500">{{ $family->email ?: 'No email' }}</div></td><td>{{ str($family->type)->headline() }}</td><td>{{ $family->phone ?: '—' }}</td><td>{{ $family->created_at?->format('d M Y') }}</td><td class="whitespace-nowrap"><a class="link text-xs" href="{{ route('responsible.edit', $family) }}">Edit &amp; link students</a><form class="ml-3 inline" method="POST" action="{{ route('responsible.destroy', $family) }}" onsubmit="return confirm('Move this unlinked family contact to the recycle bin?');">@csrf @method('DELETE')<button class="link text-xs text-rose-700">Remove</button></form></td></tr>
        @empty
            <tr><td colspan="6" class="empty">Every family contact is linked to at least one student.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="border-t border-slate-200 p-3"><x-pagination :paginator="$families" /></div>
</section>
@endsection
