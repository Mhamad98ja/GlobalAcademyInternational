@extends('layouts.app', ['title' => $registration->exists ? 'Edit registration for '.$student->full_name : 'Register '.$student->full_name])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $registration->exists ? 'Edit academic registration' : 'New academic registration' }}</h1>
        <p class="page-subtitle">
            {{ $registration->exists ? 'Update this student’s saved academic-year registration.' : 'Create a preserved academic-year record for '.$student->full_name.'.' }}
        </p>
    </div>
    <a class="btn btn-secondary" href="{{ route('students.show', $student) }}">Cancel</a>
</div>

<form class="panel" method="POST" action="{{ $registration->exists ? route('students.register.update', [$student, $registration]) : route('students.register.store', $student) }}" data-submit-lock>
    @csrf
    @if ($registration->exists)
        @method('PUT')
    @endif

    <div class="p-5">
        <div class="form-grid">
            <div>
                <label class="form-label">Academic year *</label>
                <select name="academic_year_id" required>
                    <option value="">Select year</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected(old('academic_year_id', $registration->academic_year_id) == $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Grade *</label>
                <select name="grade_id" required data-grade-select data-section-target="#student-registration-section">
                    <option value="">Select grade</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" @selected(old('grade_id', $registration->grade_id) == $grade->id)>{{ $grade->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Section</label>
                <select id="student-registration-section" name="section_id" disabled>
                    <option value="">No section</option>
                    @foreach ($grades as $grade)
                        @foreach ($grade->sections as $section)
                            <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(old('section_id', $registration->section_id) == $section->id)>{{ $grade->name }}.{{ $section->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Teacher</label>
                <select name="teacher_id">
                    <option value="">No linked teacher</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id', $registration->teacher_id) == $teacher->id)>{{ $teacher->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Registration date *</label>
                <input type="date" name="registered_on" value="{{ old('registered_on', $registration->registered_on?->format('Y-m-d') ?? today()->format('Y-m-d')) }}" required>
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3">{{ old('notes', $registration->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex justify-end border-t border-slate-200 p-5">
        <button class="btn btn-primary">{{ $registration->exists ? 'Save changes' : 'Create registration' }}</button>
    </div>
</form>
@endsection
