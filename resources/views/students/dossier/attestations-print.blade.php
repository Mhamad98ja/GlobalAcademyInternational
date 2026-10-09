<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ str($certificateType)->headline() }} · Global Academy International</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-8 text-slate-900">
@foreach($registrations as $registration)
    @php($student = $registration->student)
    @php($guardian = $student->responsiblePersons->sortByDesc(fn ($person) => $person->pivot->is_primary)->first())
    <article class="attestation-sheet {{ !$loop->last ? 'attestation-page-break' : '' }}">
        <header class="flex items-start justify-between border-b-2 border-slate-800 pb-5">
            <div class="flex items-center gap-4">
                <img class="h-16 w-16 object-contain" src="{{ asset('images/global-academy-international-logo.png') }}" alt="Global Academy International logo">
                <div><div class="text-2xl font-bold tracking-wide">{{ $school['school_name'] ?? 'Global Academy International' }}</div>@if($school['school_address'] ?? null)<div class="mt-1 text-sm text-slate-600">{{ $school['school_address'] }}</div>@endif @if($school['school_phone'] ?? null)<div class="text-sm text-slate-600">{{ $school['school_phone'] }}</div>@endif</div>
            </div>
            <div class="text-right text-sm text-slate-600">Issued {{ $referenceDate->format('d M Y') }}<br>Global Academy International Student Dossier</div>
        </header>
        <div class="py-16 text-center"><h1 class="text-2xl font-semibold uppercase tracking-[0.16em]">{{ $certificateType === 'attendance' ? 'Attendance Attestation' : 'Enrolment Attestation' }}</h1></div>
        <div class="mx-auto max-w-3xl space-y-6 text-lg leading-8"><p>To whom it may concern,</p><p>This is to certify that <strong>{{ $student->full_name }}</strong>, student number <strong>{{ $student->student_number }}</strong>, is {{ $certificateType === 'attendance' ? 'attending' : 'enrolled at' }} {{ $school['school_name'] ?? 'Global Academy International' }} for the academic year <strong>{{ $registration->academicYear->name }}</strong>.</p><p>The student is registered in <strong>{{ $registration->grade->name }}{{ $registration->section ? ', Section '.$registration->section->name : '' }}</strong>.</p>@if($includeEnrollmentDate && $student->enrollment_date)<p>The student’s Global Academy International enrolment date is <strong>{{ $student->enrollment_date->format('d M Y') }}</strong>.</p>@endif @if($guardian)<p>The responsible family contact on file is <strong>{{ $guardian->full_name }}</strong>{{ $guardian->phone ? ' ('.$guardian->phone.')' : '' }}.</p>@endif<p>This document was issued upon request on {{ $referenceDate->format('d M Y') }}.</p></div>
        <footer class="mt-28 flex justify-end"><div class="w-64 border-t border-slate-700 pt-2 text-center text-sm text-slate-600">Authorised Global Academy International signature and stamp</div></footer>
    </article>
@endforeach
<script>window.addEventListener('load',()=>window.print());</script>
</body>
</html>
