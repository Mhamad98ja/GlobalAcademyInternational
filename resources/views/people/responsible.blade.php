@extends('layouts.app', ['title' => 'Responsible persons'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Responsible persons</h1>
        <p class="page-subtitle">Create a parent, guardian, or employee contact once, then connect that person to one or more students.</p>
    </div>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(20rem,1fr)_minmax(0,1.8fr)]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Add responsible person</h2></div>
        <form method="POST" action="{{ route('responsible.store') }}" class="space-y-3 p-5" data-submit-lock>
            @csrf
            <div><label class="form-label">Full name *</label><input name="full_name" required value="{{ old('full_name') }}"></div>
            <div><label class="form-label">Type *</label><select name="type" required>@foreach (['guardian' => 'Guardian', 'father' => 'Father', 'mother' => 'Mother', 'teacher' => 'Teacher', 'administrator' => 'Administrator', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('type', 'guardian') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="form-label">Linked teacher (optional)</label><select name="teacher_id"><option value="">No linked teacher</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->full_name }}</option>@endforeach</select></div>
            <div>
                <label class="form-label">Students linked to this person</label>
                <x-student-multi-select :students="$selectedStudents" />
                <p class="mt-1 text-xs text-slate-500">Search and select every child connected to this responsible person. You can change these links later.</p>
            </div>
            <div><label class="form-label">Phone</label><input name="phone" value="{{ old('phone') }}"></div>
            <div><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}"></div>
            <div><label class="form-label">Address</label><textarea name="address" rows="2">{{ old('address') }}</textarea></div>
            <div><label class="form-label">Notes</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>
            <button class="btn btn-primary w-full">Save person</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header"><form class="flex w-full gap-2"><input name="search" value="{{ request('search') }}" placeholder="Name or phone"><button class="btn btn-secondary">Search</button></form></div>
        <div class="table-wrap">
            <table class="data-table min-w-[760px]">
                <thead><tr><th>Name</th><th>Type</th><th>Phone</th><th>Linked students</th><th>Linked teacher</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($people as $person)
                        <tr>
                            <td>{{ $person->full_name }}<div class="text-xs muted">{{ $person->email }}</div></td>
                            <td class="capitalize">{{ $person->type }}</td>
                            <td>{{ $person->phone ?: '-' }}</td>
                            <td>{{ $person->students->pluck('full_name')->join(', ') ?: 'No students linked' }}</td>
                            <td>{{ $person->teacher?->full_name ?? '-' }}</td>
                            <td><a class="link text-xs" href="{{ route('responsible.edit', $person) }}" title="Edit this person's contact details and linked students">Edit person &amp; students</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No responsible people found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-3">{{ $people->links() }}</div>
    </section>
</div>
@endsection
