@extends('layouts.app', ['title' => 'Student attestations'])

@section('content')
<div class="page-header"><div><div class="text-sm text-slate-500">Student dossier / Attestations</div><h1 class="page-title mt-1">Student attestations</h1><p class="page-subtitle">Select active students and prepare Global Academy International enrolment or attendance attestations for printing.</p></div></div>

<section class="panel"><div class="panel-header"><form method="GET" class="grid w-full gap-2 md:grid-cols-2 xl:grid-cols-5">@if(!empty($filters['student_id']))<input type="hidden" name="student_id" value="{{ $filters['student_id'] }}">@endif<select name="academic_year_id" aria-label="Academic year"><option value="">All academic years</option>@foreach($years as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}</option>@endforeach</select><select aria-label="Grade" name="grade_id" data-grade-select data-section-target="#attestation-filter-section"><option value="">All grades</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? null) == $grade->id)>{{ $grade->name }}</option>@endforeach</select><select aria-label="Section" id="attestation-filter-section" name="section_id" disabled><option value="">All sections</option>@foreach($grades as $grade)@foreach($grade->sections as $section)<option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(($filters['section_id'] ?? null) == $section->id)>{{ $grade->name }} · {{ $section->name }}</option>@endforeach @endforeach</select><input aria-label="Student name or number" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student name or number"><div class="flex gap-2"><button class="btn btn-secondary flex-1">Find students</button><a class="btn btn-secondary" href="{{ route('students.attestations') }}">Reset</a></div></form></div>
    <form method="GET" action="{{ route('students.attestations.print') }}" target="_blank" rel="noopener" data-attestation-form><div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 bg-slate-50 p-4"><div class="grid gap-3 sm:grid-cols-3"><div><label class="form-label">Attestation</label><select name="certificate_type"><option value="enrollment">Enrolment attestation</option><option value="attendance">Attendance attestation</option></select></div><div><label class="form-label">Reference date</label><input type="date" name="reference_date" value="{{ today()->format('Y-m-d') }}" required></div><label class="flex items-center gap-2 pb-2 text-sm text-slate-700"><input type="checkbox" name="include_enrollment_date" value="1"> Include enrolment date</label></div><div><p class="mb-2 text-sm text-slate-600" data-attestation-selection aria-live="polite">Select students below.</p><button class="btn btn-primary" data-attestation-print>Print selected attestations</button></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th><input type="checkbox" data-attestation-select-all aria-label="Select all students"></th><th>Student</th><th>Student no.</th><th>Academic year</th><th>Class</th></tr></thead><tbody>@forelse($registrations as $registration)<tr><td><input type="checkbox" aria-label="Select {{ $registration->student->full_name }}" name="registration_ids[]" value="{{ $registration->id }}" data-attestation-row-select></td><td><a class="link" href="{{ route('students.show', $registration->student) }}">{{ $registration->student->full_name }}</a></td><td>{{ $registration->student->student_number }}</td><td>{{ $registration->academicYear->name }}</td><td>{{ $registration->grade->name }}{{ $registration->section ? ' · '.$registration->section->name : '' }}</td></tr>@empty<tr><td colspan="5" class="empty">No active registrations match this filter.</td></tr>@endforelse</tbody></table></div></form>
    <div class="border-t border-slate-200 p-3"><x-pagination :paginator="$registrations" /></div>
</section>
<script>
(() => {
    const form = document.querySelector('[data-attestation-form]');
    const all = form.querySelector('[data-attestation-select-all]');
    const rows = [...form.querySelectorAll('[data-attestation-row-select]')];
    const update = () => {
        const count = rows.filter(input => input.checked).length;
        form.querySelector('[data-attestation-selection]').textContent = count + ' student(s) selected';
        form.querySelector('[data-attestation-print]').disabled = count === 0;
        all.checked = rows.length > 0 && count === rows.length;
        all.indeterminate = count > 0 && count < rows.length;
    };
    all.addEventListener('change', () => { rows.forEach(input => input.checked = all.checked); update(); });
    rows.forEach(input => input.addEventListener('change', update));
    update();
})();
</script>
@endsection
