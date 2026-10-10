@extends('layouts.app', ['title' => 'Academic operations'])

@section('content')
@php
    $activeSection = $activeSection ?? 'overview';
    $operationSections = [
        'overview' => ['label' => 'Operations overview', 'description' => 'Choose one focused setup area.', 'route' => 'school-academic.index'],
        'subjects' => ['label' => 'Subjects', 'description' => 'Create the subjects taught by the school.', 'route' => 'school-academic.subjects'],
        'terms' => ['label' => 'Terms / semesters', 'description' => 'Define dates and status for each academic period.', 'route' => 'school-academic.terms'],
        'assignments' => ['label' => 'Teacher subject assignments', 'description' => 'Connect a teacher, subject, class, and section.', 'route' => 'school-academic.assignments'],
        'assessments' => ['label' => 'Assessments', 'description' => 'Configure tests, exams, and the review queue.', 'route' => 'school-academic.assessments'],
    ];
@endphp
@if(request('module') === 'students')<div class="mb-4 text-sm text-slate-500"><a class="link" href="{{ route('workspaces.show', ['workspace' => 'students']) }}">Student dossier</a> / <a class="link" href="{{ route('students.tools', ['section' => 'references']) }}">References</a></div>@endif
<div class="page-header">
    <div>
        <div class="text-sm text-slate-500"><a class="link" href="{{ route('academic.index') }}">Academic structure</a> / <a class="link" href="{{ route('school-academic.index') }}">Operations</a>@if ($activeSection !== 'overview') / <strong class="text-slate-700">{{ $operationSections[$activeSection]['label'] }}</strong>@endif</div>
        <h1 class="page-title mt-1">Academic operations</h1>
        <p class="page-subtitle">{{ $operationSections[$activeSection]['description'] ?? 'Manage academic setup in focused workspaces.' }} Each area has its own create, list, and edit workflow.</p>
    </div>
</div>

<nav class="mb-6 grid gap-3 md:grid-cols-2 xl:grid-cols-5" aria-label="Academic operations sections">
    @foreach ($operationSections as $key => $section)
        <a href="{{ route($section['route']) }}" class="rounded-lg border p-4 transition hover:-translate-y-0.5 hover:shadow-sm {{ $activeSection === $key ? 'border-teal-600 bg-teal-50 ring-1 ring-teal-600/20' : 'border-slate-200 bg-white hover:border-teal-300' }}">
            <span class="block text-sm font-semibold {{ $activeSection === $key ? 'text-teal-800' : 'text-slate-800' }}">{{ $section['label'] }}</span>
            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $section['description'] }}</span>
            <span class="mt-3 block text-xs font-semibold uppercase tracking-wide {{ $activeSection === $key ? 'text-teal-700' : 'text-slate-400' }}">{{ $activeSection === $key ? 'Current area' : 'Open area' }} <span aria-hidden="true">→</span></span>
        </a>
    @endforeach
</nav>

@if ($activeSection === 'overview')
    <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach (['subjects' => ['count' => $subjects->count(), 'label' => 'Subjects ready', 'note' => 'Active and inactive subject records'], 'terms' => ['count' => $terms->count(), 'label' => 'Terms configured', 'note' => 'Academic periods across school years'], 'assignments' => ['count' => $assignments->where('is_active', true)->count(), 'label' => 'Active assignments', 'note' => 'Teacher-to-subject class links'], 'assessments' => ['count' => $assessments->count(), 'label' => 'Assessments', 'note' => 'Tests, exams, and evaluation setup']] as $key => $stat)
            <a href="{{ route($operationSections[$key]['route']) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-md">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</span>
                <strong class="mt-2 block text-3xl font-semibold tracking-tight text-slate-900">{{ number_format($stat['count']) }}</strong>
                <span class="mt-1 block text-sm text-slate-500">{{ $stat['note'] }}</span>
                <span class="mt-4 block text-sm font-semibold text-teal-700">Manage {{ strtolower($operationSections[$key]['label']) }} →</span>
            </a>
        @endforeach
    </section>
@endif

@if ($activeSection === 'subjects')
    <section class="panel">
        <div class="panel-header"><h2 id="subjects" class="panel-title scroll-mt-20">Subjects</h2></div>
        <form method="POST" action="{{ route('school-academic.subjects.store') }}" class="space-y-3 border-b border-slate-200 p-5">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <input name="code" placeholder="Code (optional)" value="{{ old('code') }}">
                <input name="name" required placeholder="Subject name" value="{{ old('name') }}">
            </div>
            <textarea name="description" rows="2" placeholder="Description (optional)">{{ old('description') }}</textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <button class="btn btn-primary">Add subject</button>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Subject</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr>
                            <td>{{ $subject->code ?: '—' }}</td>
                            <td>{{ $subject->name }}</td>
                            <td><span class="badge {{ $subject->is_active ? 'badge-green' : 'badge-amber' }}">{{ $subject->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Add the subjects your school teaches.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

@if ($activeSection === 'terms')
    <section class="panel">
        <div class="panel-header"><h2 id="terms" class="panel-title scroll-mt-20">Terms / semesters</h2></div>
        <form method="POST" action="{{ route('school-academic.terms.store') }}" class="space-y-3 border-b border-slate-200 p-5">
            @csrf
            <select name="academic_year_id" required>
                <option value="">Academic year</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                @endforeach
            </select>
            <div class="grid gap-3 sm:grid-cols-2"><input name="name" required placeholder="Semester 1"><input name="type" required value="semester" placeholder="Type"></div>
            <div class="grid gap-3 sm:grid-cols-2"><input type="date" name="starts_on" required><input type="date" name="ends_on" required></div>
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="status"><option value="draft">Draft</option><option value="open">Open</option><option value="closed">Closed</option></select>
                <input type="number" min="0" name="sort_order" value="0" placeholder="Order">
            </div>
            <button class="btn btn-primary">Add term</button>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Year / term</th><th>Dates</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($terms as $term)
                        <tr>
                            <td><div>{{ $term->name }}</div><div class="text-xs text-slate-500">{{ $term->academicYear?->name }} &middot; {{ str($term->type)->headline() }}</div></td>
                            <td>{{ $term->starts_on->format('d M') }} &ndash; {{ $term->ends_on->format('d M') }}</td>
                            <td><span class="badge badge-blue">{{ str($term->status)->headline() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No terms configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

@if ($activeSection === 'assignments')
    <section class="panel">
        <div class="panel-header"><h2 id="assignments" class="panel-title scroll-mt-20">Teacher subject assignments</h2></div>
        <form method="POST" action="{{ route('school-academic.assignments.store') }}" class="space-y-3 border-b border-slate-200 p-5">
            @csrf
            <select name="academic_year_id" required>
                <option value="">Academic year</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                @endforeach
            </select>
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="teacher_id" required>
                    <option value="">Teacher</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                    @endforeach
                </select>
                <select name="subject_id" required>
                    <option value="">Subject</option>
                    @foreach ($subjects->where('is_active', true) as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="grade_id" required data-grade-select data-section-target="#assignment-section">
                    <option value="">Grade</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                    @endforeach
                </select>
                <select id="assignment-section" name="section_id" disabled>
                    <option value="">All sections in grade</option>
                    @foreach ($grades as $grade)
                        @foreach ($grade->sections as $section)
                            <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}">{{ $grade->name }} &middot; {{ $section->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2"><input type="number" min="0" max="50" name="required_periods_per_week" value="0" placeholder="Required periods / week"><input type="number" min="1" max="60" name="weekly_period_limit" placeholder="Teacher weekly limit"></div>
            <textarea name="notes" rows="2" placeholder="Notes (optional)"></textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <button class="btn btn-primary">Assign subject to teacher</button>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Teacher / subject</th><th>Class</th><th>Weekly plan</th></tr></thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td><div>{{ $assignment->teacher?->full_name }}</div><div class="text-xs text-slate-500">{{ $assignment->academicYear?->name }} &middot; {{ $assignment->subject?->name }}</div></td>
                            <td>{{ $assignment->grade?->name }} &middot; {{ $assignment->section?->name ?? 'All sections' }}</td>
                            <td>{{ $assignment->required_periods_per_week }} required @if ($assignment->weekly_period_limit) &middot; {{ $assignment->weekly_period_limit }} max @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">No teacher assignments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif

@if ($activeSection === 'assessments')
<section class="panel mt-6">
    <div class="panel-header"><div><h2 id="assessments" class="panel-title scroll-mt-20">Assessments</h2><p class="text-sm text-slate-500">Every assessment belongs to the subject, teacher, and class where it will be delivered.</p></div></div>
    <form method="POST" action="{{ route('school-academic.assessments.store') }}" class="grid gap-3 border-b border-slate-200 p-5 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <select name="academic_year_id" required>
            <option value="">Academic year</option>
            @foreach ($years as $year)
                <option value="{{ $year->id }}">{{ $year->name }}</option>
            @endforeach
        </select>
        <select name="academic_term_id">
            <option value="">No term</option>
            @foreach ($terms as $term)
                <option value="{{ $term->id }}">{{ $term->academicYear?->name }} &middot; {{ $term->name }}</option>
            @endforeach
        </select>
        <select name="teacher_assignment_id" required>
            <option value="">Teacher subject / class assignment</option>
            @foreach ($assignments->where('is_active', true) as $assignment)
                <option value="{{ $assignment->id }}">{{ $assignment->teacher?->full_name }} · {{ $assignment->subject?->name }} · {{ $assignment->grade?->name }}{{ $assignment->section ? ' '.$assignment->section->name : '' }}</option>
            @endforeach
        </select>
        <input name="name" required placeholder="Midterm / Monthly Test 1">
        <input name="type" required value="test" placeholder="Assessment type">
        <input type="number" step="0.01" min="0.01" name="maximum_score" value="100" required placeholder="Maximum score">
        <input type="number" step="0.001" min="0.001" max="10" name="weight" value="1" required placeholder="Weight / coefficient">
        <input type="date" name="assessment_date">
        <select name="status"><option value="draft">Draft</option><option value="open">Open</option><option value="closed">Closed</option></select>
        <textarea name="notes" class="md:col-span-2" rows="2" placeholder="Notes (optional)"></textarea>
        <div class="flex items-end"><button class="btn btn-primary w-full">Create assessment</button></div>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Assessment</th><th>Scope</th><th>Date / maximum</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($assessments as $assessment)
                    <tr>
                        <td><div>{{ $assessment->name }}</div><div class="text-xs text-slate-500">{{ str($assessment->type)->headline() }} @if ($assessment->term) &middot; {{ $assessment->term->name }} @endif</div></td>
                        <td>{{ $assessment->assignment?->teacher?->full_name ?? 'General' }} &middot; {{ $assessment->assignment?->subject?->name ?? $assessment->subject?->name ?? 'All subjects' }} @if ($assessment->assignment?->grade ?? $assessment->grade)<div class="text-xs text-slate-500">{{ ($assessment->assignment?->grade ?? $assessment->grade)?->name }} @if ($assessment->assignment?->section ?? $assessment->section) &middot; {{ ($assessment->assignment?->section ?? $assessment->section)?->name }} @endif</div>@endif</td>
                        <td>{{ $assessment->assessment_date?->format('d M Y') ?? 'Unscheduled' }}<div class="text-xs text-slate-500">{{ $assessment->maximum_score }} maximum · weight {{ rtrim(rtrim(number_format((float) $assessment->weight, 3), '0'), '.') }}×</div></td>
                        <td><span class="badge {{ $assessment->status === 'open' ? 'badge-green' : 'badge-amber' }}">{{ str($assessment->status)->headline() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No assessments configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel mt-6">
    <div class="panel-header"><div><h2 class="panel-title">Assessment review queue</h2><p class="text-sm text-slate-500">Approve submitted teacher evaluations to publish them to students, or return them with feedback.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Evaluation</th><th>Teacher / class</th><th>Status</th><th>Decision</th></tr></thead><tbody>
    @forelse ($assessments->where('review_status', 'submitted') as $assessment)
        <tr><td><strong>{{ $assessment->name }}</strong><div class="text-xs text-slate-500">{{ str($assessment->type)->headline() }} · over {{ $assessment->maximum_score }}</div></td><td>{{ $assessment->assignment?->teacher?->full_name }} · {{ $assessment->assignment?->grade?->name }}{{ $assessment->assignment?->section ? ' / '.$assessment->assignment->section->name : '' }} · {{ $assessment->assignment?->subject?->name }}</td><td><span class="badge badge-amber">Submitted</span></td><td class="flex gap-2"><form method="POST" action="{{ route('school-academic.assessments.approve', $assessment) }}">@csrf<button class="btn btn-primary btn-small">Approve</button></form><form method="POST" action="{{ route('school-academic.assessments.reject', $assessment) }}" class="flex gap-2"><input name="rejection_reason" required placeholder="Reason for return"><button class="btn btn-secondary btn-small">Reject</button></form></td></tr>
    @empty
        <tr><td colspan="4" class="empty">No submitted evaluations are waiting for review.</td></tr>
    @endforelse
    </tbody></table></div>
</section>

<section class="panel mt-6">
    <div class="panel-header"><div><h2 class="panel-title">Edit academic configuration</h2><p class="text-sm text-slate-500">Historical records remain intact; changes here affect future work.</p></div></div>
    <div class="grid gap-4 p-5 xl:grid-cols-2">
        @if ($activeSection === 'subjects')
        <div class="space-y-2">
            <h3 class="font-medium">Subjects</h3>
            @foreach ($subjects as $subject)
                <details class="rounded-md border border-slate-200 p-3">
                    <summary class="cursor-pointer font-medium">{{ $subject->name }}</summary>
                    <form method="POST" action="{{ route('school-academic.subjects.update', $subject) }}" class="mt-3 grid gap-2">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-2"><input name="code" value="{{ $subject->code }}" placeholder="Code"><input name="name" value="{{ $subject->name }}" required></div>
                        <textarea name="description" rows="2">{{ $subject->description }}</textarea>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($subject->is_active)> Active</label>
                        <button class="btn btn-secondary btn-small">Update subject</button>
                    </form>
                </details>
            @endforeach
        </div>
        @endif

        @if ($activeSection === 'terms')
        <div class="space-y-2">
            <h3 class="font-medium">Terms</h3>
            @foreach ($terms as $term)
                <details class="rounded-md border border-slate-200 p-3">
                    <summary class="cursor-pointer font-medium">{{ $term->academicYear?->name }} &middot; {{ $term->name }}</summary>
                    <form method="POST" action="{{ route('school-academic.terms.update', $term) }}" class="mt-3 grid gap-2">
                        @csrf
                        @method('PUT')
                        <select name="academic_year_id">
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" @selected($term->academic_year_id === $year->id)>{{ $year->name }}</option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-2 gap-2"><input name="name" value="{{ $term->name }}" required><input name="type" value="{{ $term->type }}" required></div>
                        <div class="grid grid-cols-2 gap-2"><input type="date" name="starts_on" value="{{ $term->starts_on->format('Y-m-d') }}" required><input type="date" name="ends_on" value="{{ $term->ends_on->format('Y-m-d') }}" required></div>
                        <select name="status">
                            @foreach (['draft', 'open', 'closed'] as $status)
                                <option value="{{ $status }}" @selected($term->status === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="sort_order" value="{{ $term->sort_order }}">
                        <button class="btn btn-secondary btn-small">Update term</button>
                    </form>
                </details>
            @endforeach
        </div>
        @endif

        @if ($activeSection === 'assessments')
        <div class="space-y-2">
            <h3 class="font-medium">Assessments</h3>
            @foreach ($assessments as $assessment)
                <details class="rounded-md border border-slate-200 p-3">
                    <summary class="cursor-pointer font-medium">{{ $assessment->name }} &middot; {{ $assessment->academicYear?->name }}</summary>
                    <form method="POST" action="{{ route('school-academic.assessments.update', $assessment) }}" class="mt-3 grid gap-2">
                        @csrf
                        @method('PUT')
                        <select name="academic_year_id">
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" @selected($assessment->academic_year_id === $year->id)>{{ $year->name }}</option>
                            @endforeach
                        </select>
                        <select name="academic_term_id">
                            <option value="">No term</option>
                            @foreach ($terms as $term)
                                <option value="{{ $term->id }}" @selected($assessment->academic_term_id === $term->id)>{{ $term->name }}</option>
                            @endforeach
                        </select>
                        <select name="teacher_assignment_id">
                            <option value="">Legacy/general assessment — choose a subject assignment to link it</option>
                            @foreach ($assignments as $assignment)
                                <option value="{{ $assignment->id }}" @selected($assessment->teacher_assignment_id === $assignment->id)>{{ $assignment->teacher?->full_name }} · {{ $assignment->subject?->name }} · {{ $assignment->grade?->name }}{{ $assignment->section ? ' '.$assignment->section->name : '' }}</option>
                            @endforeach
                        </select>
                        <input name="name" value="{{ $assessment->name }}" required>
                        <div class="grid grid-cols-3 gap-2"><input name="type" value="{{ $assessment->type }}" required><input type="number" step=".01" name="maximum_score" value="{{ $assessment->maximum_score }}" required><input type="number" step=".001" min=".001" max="10" name="weight" value="{{ $assessment->weight }}" required></div>
                        <input type="date" name="assessment_date" value="{{ $assessment->assessment_date?->format('Y-m-d') }}">
                        <select name="status">
                            @foreach (['draft', 'open', 'closed'] as $status)
                                <option value="{{ $status }}" @selected($assessment->status === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                        <textarea name="notes" rows="2">{{ $assessment->notes }}</textarea>
                        <button class="btn btn-secondary btn-small">Update assessment</button>
                    </form>
                </details>
            @endforeach
        </div>
        @endif

        @if ($activeSection === 'assignments')
        <div class="space-y-2">
            <h3 class="font-medium">Teacher assignments</h3>
            @foreach ($assignments as $assignment)
                <details class="rounded-md border border-slate-200 p-3">
                    <summary class="cursor-pointer font-medium">{{ $assignment->teacher?->full_name }} &middot; {{ $assignment->subject?->name }} &middot; {{ $assignment->grade?->name }} {{ $assignment->section?->name }}</summary>
                    <form method="POST" action="{{ route('school-academic.assignments.update', $assignment) }}" class="mt-3 grid gap-2">
                        @csrf
                        @method('PUT')
                        <select name="academic_year_id">
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" @selected($assignment->academic_year_id === $year->id)>{{ $year->name }}</option>
                            @endforeach
                        </select>
                        <select name="teacher_id">
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected($assignment->teacher_id === $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select>
                        <select name="subject_id">
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected($assignment->subject_id === $subject->id)>{{ $subject->name }}</option>
                            @endforeach
                        </select>
                        <select name="grade_id">
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}" @selected($assignment->grade_id === $grade->id)>{{ $grade->name }}</option>
                            @endforeach
                        </select>
                        <select name="section_id">
                            <option value="">All sections</option>
                            @foreach ($grades as $grade)
                                @foreach ($grade->sections as $section)
                                    <option value="{{ $section->id }}" @selected($assignment->section_id === $section->id)>{{ $grade->name }} &middot; {{ $section->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        <div class="grid grid-cols-2 gap-2"><input type="number" min="0" name="required_periods_per_week" value="{{ $assignment->required_periods_per_week }}"><input type="number" min="1" name="weekly_period_limit" value="{{ $assignment->weekly_period_limit }}"></div>
                        <textarea name="notes" rows="2">{{ $assignment->notes }}</textarea>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($assignment->is_active)> Active</label>
                        <button class="btn btn-secondary btn-small">Update assignment</button>
                    </form>
                </details>
            @endforeach
        </div>
        @endif
    </div>
</section>
@endif
@endsection
