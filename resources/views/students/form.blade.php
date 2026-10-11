@extends('layouts.app', ['title' => $student->exists ? 'Edit student' : 'Add student'])

@section('content')
<div class="page-header">
    <div><h1 class="page-title">{{ $student->exists ? 'Edit student' : 'Add student' }}</h1><p class="page-subtitle">A profile is kept through all academic years.</p></div>
    <div class="flex flex-wrap gap-2">
        @if($student->exists)
            @php($latestRegistration = $student->registrations()->where('status', 'active')->latest('registered_on')->first() ?? $student->registrations()->latest('registered_on')->first())
            <a class="btn btn-primary" href="{{ $latestRegistration ? route('students.register.edit', [$student, $latestRegistration]) : route('students.register.create', $student) }}">{{ $latestRegistration ? 'Edit academic registration' : 'Academic registration' }}</a>
        @endif
        <a class="btn btn-secondary" href="{{ $student->exists ? route('students.show', $student) : route('students.index') }}">Cancel</a>
    </div>
</div>

<form class="panel" method="POST" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}" data-submit-lock>
    @csrf
    @if($student->exists) @method('PUT') @endif
    @if(isset($responsiblePerson) && $responsiblePerson?->id)<input type="hidden" name="responsible_person_id" value="{{ $responsiblePerson->id }}"><div class="mx-5 mt-5 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">Adding a student for <strong>{{ $responsiblePerson->full_name }}</strong>. You can add more students from the parent record later.</div>@endif
    <div class="space-y-6 p-5">
        <div><h2 class="panel-title">Identity</h2><div class="form-grid mt-4">
            <div><label class="form-label">Student number</label><input name="student_number" value="{{ old('student_number', $student->student_number) }}" placeholder="Generated if blank"></div>
            <div><label class="form-label">Registration number</label><input name="registration_number" value="{{ old('registration_number', $student->registration_number) }}"></div>
            <div><label class="form-label">First name *</label><input name="first_name" required value="{{ old('first_name', $student->first_name) }}"></div>
            <div><label class="form-label">Middle name</label><input name="middle_name" value="{{ old('middle_name', $student->middle_name) }}"></div>
            <div><label class="form-label">Last name *</label><input name="last_name" required value="{{ old('last_name', $student->last_name) }}"></div>
            <div><label class="form-label">Gender</label><select name="gender"><option value="">Not specified</option>@foreach(['male' => 'Male', 'female' => 'Female'] as $value => $label)<option value="{{ $value }}" @selected(old('gender', $student->gender) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="form-label">Date of birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $student->date_of_birth?->format('Y-m-d')) }}"></div>
            <div><label class="form-label">Enrollment date</label><input type="date" name="enrollment_date" value="{{ old('enrollment_date', $student->enrollment_date?->format('Y-m-d')) }}"></div>
        </div></div>

        <div><h2 class="panel-title">Student portal account</h2><p class="mt-1 text-sm text-slate-500">The student uses these credentials to sign in and see teacher agenda items, announcements, assessments, and grades.</p><div class="form-grid mt-4">
            <div><label class="form-label">Login email *</label><input type="email" name="email" value="{{ old('email', $student->email) }}" autocomplete="email" required></div>
            <div><label class="form-label">Password {{ $student->exists && $student->portalUser ? '(leave blank to keep current password)' : '*' }}</label><input type="password" name="password" autocomplete="new-password" {{ $student->exists && $student->portalUser ? '' : 'required' }}></div>
            <div><label class="form-label">Confirm password {{ $student->exists && $student->portalUser ? '' : '*' }}</label><input type="password" name="password_confirmation" autocomplete="new-password" {{ $student->exists && $student->portalUser ? '' : 'required' }}></div>
        </div></div>

        <div><h2 class="panel-title">Contact</h2><div class="form-grid mt-4">
            <div><label class="form-label">Phone</label><input name="phone" value="{{ old('phone', $student->phone) }}"></div>
            <div class="md:col-span-2"><label class="form-label">Address details</label><div class="form-grid"><input name="area" placeholder="Area" value="{{ old('area', $student->area) }}"><input name="street" placeholder="Street" value="{{ old('street', $student->street) }}"><input name="area_number" placeholder="Area number (text)" value="{{ old('area_number', $student->area_number) }}"><input name="street_number" placeholder="Street number (text)" value="{{ old('street_number', $student->street_number) }}"><input name="building_name" placeholder="Building name" value="{{ old('building_name', $student->building_name) }}"><input name="building_number" placeholder="Building number" value="{{ old('building_number', $student->building_number) }}"><input name="apartment" placeholder="Apartment" value="{{ old('apartment', $student->apartment) }}"><textarea name="address" rows="2" placeholder="Additional address details">{{ old('address', $student->address) }}</textarea></div></div>
            <div><label class="form-label">Previous school</label><input name="previous_school" value="{{ old('previous_school', $student->previous_school) }}"></div>
            <div class="md:col-span-2"><label class="form-label">Notes</label><textarea name="notes" rows="3">{{ old('notes', $student->notes) }}</textarea></div>
        </div></div>
    </div>
    <div class="flex justify-end gap-3 border-t border-slate-200 p-5"><a class="btn btn-secondary" href="{{ $student->exists ? route('students.show', $student) : route('students.index') }}">Cancel</a><button class="btn btn-primary">{{ $student->exists ? 'Save student' : 'Create student and portal account' }}</button></div>
</form>
@endsection
