@extends('layouts.app', ['title' => 'Book packages'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Class book packages</h1>
        <p class="page-subtitle">Required and optional books by academic year, grade, and section.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('class-lists.create') }}">New package</a>
</div>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table min-w-[760px]">
            <thead>
                <tr><th>Package</th><th>Academic year</th><th>Grade</th><th>Section</th><th>Books</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($lists as $list)
                    <tr>
                        <td class="font-medium">{{ $list->name }}</td>
                        <td>{{ $list->academicYear->name }}</td>
                        <td>{{ $list->grade->name }}</td>
                        <td>{{ $list->section?->name ?? 'All sections' }}</td>
                        <td>{{ $list->items->count() }}</td>
                        <td><span class="badge {{ $list->is_active ? 'badge-green' : 'badge-red' }}">{{ $list->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td><a class="link text-xs" href="{{ route('class-lists.edit', $list) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No class book packages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 p-3">{{ $lists->links() }}</div>
</section>
@endsection
