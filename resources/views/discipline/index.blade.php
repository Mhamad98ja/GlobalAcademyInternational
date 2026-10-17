@extends('layouts.app', ['title' => 'Student actions'])

@section('content')
<div class="page-header"><div><h1 class="page-title">Student actions & incidents</h1><p class="page-subtitle">Record discipline, safeguarding, positive behaviour, consequences, follow-up, and parent communication in the student archive.</p></div></div>

<div class="grid gap-6 2xl:grid-cols-[1.1fr_1.9fr]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">New action record</h2></div>
        <form method="POST" action="{{ route('discipline.store') }}" enctype="multipart/form-data" class="space-y-3 p-5" data-submit-lock>
            @csrf
            <x-student-picker name="student_id" :selected-id="old('student_id')" :selected-label="$students->firstWhere('id', old('student_id'))?->full_name" required />
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="action_category_id" data-action-category-select data-action-type-target="#action-type">
                    <option value="">Category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <select id="action-type" name="action_type_id" disabled>
                    <option value="">Action type</option>
                    @foreach ($categories as $category)
                        @foreach ($category->types as $type)
                            <option value="{{ $type->id }}" data-action-category-id="{{ $category->id }}">{{ $category->name }} &middot; {{ $type->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2"><input type="date" name="incident_date" value="{{ today()->toDateString() }}" required><input type="time" name="incident_time"></div>
            <input name="location" placeholder="Location">
            <textarea name="description" rows="3" required placeholder="What happened?"></textarea>
            <textarea name="action_taken" rows="2" placeholder="Action taken"></textarea>
            <textarea name="consequence" rows="2" placeholder="Consequence"></textarea>
            <textarea name="follow_up" rows="2" placeholder="Follow-up"></textarea>
            <select name="reported_by_teacher_id">
                <option value="">Reporting staff member</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                @endforeach
            </select>
            <select name="status"><option value="open">Open</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="parent_notified" value="1"> Parent notified</label>
            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
            <button class="btn btn-primary w-full">Save action record</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <form method="GET" class="grid w-full gap-2 md:grid-cols-3">
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search location or description">
                <x-student-picker name="student_id" :selected-id="$filters['student_id'] ?? null" :selected-label="$students->firstWhere('id', $filters['student_id'] ?? null)?->full_name" placeholder="Filter by student name or number" />
                <select name="action_type_id">
                    <option value="">All action types</option>
                    @foreach ($categories as $category)
                        @foreach ($category->types as $type)
                            <option value="{{ $type->id }}" @selected(($filters['action_type_id'] ?? null) == $type->id)>{{ $category->name }} &middot; {{ $type->name }}</option>
                        @endforeach
                    @endforeach
                </select>
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach (['open', 'resolved', 'dismissed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"><button class="btn btn-secondary">Filter</button>
            </form>
        </div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Student</th><th>Action</th><th>Description</th><th>Status</th></tr></thead><tbody>
            @forelse ($incidents as $incident)
                <tr><td>{{ $incident->incident_date?->format('d M Y') }}<div class="text-xs text-slate-500">{{ $incident->incident_time }}</div></td><td><a class="link" href="{{ route('students.show', $incident->student) }}">{{ $incident->student?->full_name }}</a></td><td>{{ $incident->category?->name }}<div class="text-xs text-slate-500">{{ $incident->type?->name }}</div></td><td class="max-w-sm whitespace-normal">{{ $incident->description }}</td><td><span class="badge {{ $incident->status === 'resolved' ? 'badge-green' : 'badge-amber' }}">{{ str($incident->status)->headline() }}</span></td></tr>
            @empty
                <tr><td colspan="5" class="empty">No action records match the selected filters.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="border-t border-slate-200 p-3">{{ $incidents->links() }}</div>
    </section>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="panel"><div class="panel-header"><h2 id="action-references" class="panel-title scroll-mt-20">Create action category</h2></div><form method="POST" action="{{ route('discipline.categories.store') }}" class="flex gap-2 p-5">@csrf<input name="name" required placeholder="e.g. Discipline, Positive behaviour"><input name="kind" required value="discipline" placeholder="Kind"><button class="btn btn-secondary">Add</button></form></section>
    <section class="panel"><div class="panel-header"><h2 class="panel-title">Create action type</h2></div><form method="POST" action="{{ route('discipline.types.store') }}" class="flex gap-2 p-5">@csrf<select name="action_category_id" required><option value="">Category</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}">{{ $category->name }}</option>
        @endforeach
    </select><input name="name" required placeholder="e.g. Bullying, Award"><button class="btn btn-secondary">Add</button></form></section>
</div>
@endsection
