@extends('layouts.app', ['title' => 'Mark student as left school'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Mark as left school</h1>
        <p class="page-subtitle">{{ $student->full_name }}’s profile, invoices, responsible persons, and academic history will be kept.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('students.show', $student) }}">Cancel</a>
</div>

<section class="panel max-w-3xl">
    @if ($student->registrations->isEmpty())
        <div class="empty">This student has no active academic registration to mark as withdrawn.</div>
    @else
        <form method="POST" action="{{ route('students.leave.store', $student) }}" data-submit-lock>
            @csrf
            <div class="space-y-5 p-5">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    The active registration{{ $student->registrations->count() === 1 ? '' : 's' }} below will be marked <strong>Withdrawn</strong>. The student profile will not be deleted.
                </div>
                <ul class="list-disc space-y-1 pl-5 text-sm text-slate-600">
                    @foreach ($student->registrations as $registration)
                        <li>{{ $registration->academicYear?->name }} · {{ $registration->grade?->name }}{{ $registration->section ? ' · '.$registration->section->name : '' }}</li>
                    @endforeach
                </ul>
                <div class="form-grid">
                    <div>
                        <label class="form-label" for="withdrawn-on">Leaving date *</label>
                        <input id="withdrawn-on" type="date" name="withdrawn_on" value="{{ old('withdrawn_on', today()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label" for="withdrawal-reason">Reason for leaving</label>
                        <textarea id="withdrawal-reason" name="withdrawal_reason" rows="4" placeholder="For example: transferred to another school">{{ old('withdrawal_reason') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 p-5">
                <a class="btn btn-secondary" href="{{ route('students.show', $student) }}">Cancel</a>
                <button class="btn btn-primary">Save leaving record</button>
            </div>
        </form>
    @endif
</section>
@endsection
