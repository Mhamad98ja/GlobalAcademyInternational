@extends('layouts.app', ['title' => 'Edit responsible person'])

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Edit responsible person</h1><p class="page-subtitle">Update the contact and the students connected to this person.</p></div>
    <a class="btn btn-secondary" href="{{ route('responsible.index') }}">Cancel</a>
</div>

<form method="POST" action="{{ route('responsible.update', $person) }}" class="panel max-w-3xl" data-submit-lock>
    @csrf
    @method('PUT')
    <div class="space-y-4 p-5">
        <div class="form-grid">
            <div><label class="form-label">Full name *</label><input name="full_name" required value="{{ old('full_name', $person->full_name) }}"></div>
            <div><label class="form-label">Type *</label><select name="type" required>@foreach (['guardian' => 'Guardian', 'father' => 'Father', 'mother' => 'Mother', 'teacher' => 'Teacher', 'administrator' => 'Administrator', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $person->type) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="form-label">Linked teacher (optional)</label><select name="teacher_id"><option value="">No linked teacher</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id', $person->teacher_id) == $teacher->id)>{{ $teacher->full_name }}</option>@endforeach</select></div>
            <div><label class="form-label">Phone</label><input name="phone" value="{{ old('phone', $person->phone) }}"></div>
            <div><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $person->email) }}"></div>
            <div><label class="form-label">Address</label><textarea name="address" rows="2">{{ old('address', $person->address) }}</textarea></div>
        </div>
        <div>
            <label class="form-label">Students linked to this person</label>
            <x-student-multi-select :students="$selectedStudents" />
            <p class="mt-1 text-xs text-slate-500">Search and select every student connected to this person.</p>
        </div>
        <div><label class="form-label">Notes</label><textarea name="notes" rows="3">{{ old('notes', $person->notes) }}</textarea></div>
    </div>
    <div class="flex justify-end border-t border-slate-200 p-5"><button class="btn btn-primary">Save person</button></div>
</form>
@endsection
