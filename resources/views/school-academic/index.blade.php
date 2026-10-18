@extends('layouts.app', ['title' => 'Academic operations'])

@section('content')
@php
    $activeSection = $activeSection ?? 'overview';
    $operationSections = [
        'subjects' => ['label' => 'Subjects', 'description' => 'Create the subjects taught by the school.', 'route' => 'school-academic.subjects'],
        'assignments' => ['label' => 'Teacher subject assignments', 'description' => 'Connect a teacher, subject, class, and section.', 'route' => 'school-academic.assignments'],
    ];
@endphp
<div class="page-header">
    <div>
        <h1 class="page-title mt-1">Academic operations</h1>
        <p class="page-subtitle">{{ $operationSections[$activeSection]['description'] ?? 'Manage academic setup in focused workspaces.' }} Each area has its own create, list, and edit workflow.</p>
    </div>
</div>

@if ($activeSection === 'overview')
<nav class="mb-6 grid gap-3 md:grid-cols-2" aria-label="Academic operations sections">
    @foreach ($operationSections as $key => $section)
        @if ($section['navigation'] ?? true)
        <a href="{{ route($section['route']) }}" class="rounded-lg border p-4 transition hover:-translate-y-0.5 hover:shadow-sm {{ $activeSection === $key ? 'border-teal-600 bg-teal-50 ring-1 ring-teal-600/20' : 'border-slate-200 bg-white hover:border-teal-300' }}">
            <span class="block text-sm font-semibold {{ $activeSection === $key ? 'text-teal-800' : 'text-slate-800' }}">{{ $section['label'] }}</span>
            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $section['description'] }}</span>
            <span class="mt-3 block text-xs font-semibold uppercase tracking-wide {{ $activeSection === $key ? 'text-teal-700' : 'text-slate-400' }}">{{ $activeSection === $key ? 'Current area' : 'Open area' }} <span aria-hidden="true">→</span></span>
        </a>
        @endif
    @endforeach
</nav>
@endif

@if (false)
    <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach (['subjects' => ['count' => $subjects->count(), 'label' => 'Subjects ready', 'note' => 'Active and inactive subject records'], 'assignments' => ['count' => $assignments->where('is_active', true)->count(), 'label' => 'Active assignments', 'note' => 'Teacher-to-subject class links']] as $key => $stat)
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

@if ($activeSection === 'assignments')
@if (! $selectedTeacher)
    <section class="panel">
        <div class="panel-header"><div><h2 id="assignments" class="panel-title scroll-mt-20">Teachers</h2><p class="mt-1 text-sm text-slate-500">Select a teacher to configure their subject assignments.</p></div></div>
        <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($teachers as $teacher)
                <a href="{{ route('school-academic.teacher-assignments', $teacher) }}" class="flex items-center justify-between rounded-md border border-slate-200 bg-white px-4 py-3 font-medium text-slate-700 transition hover:border-teal-400 hover:bg-teal-50 hover:text-teal-800">
                    <span>{{ $teacher->full_name }}</span><span aria-hidden="true">&rarr;</span>
                </a>
            @empty
                <p class="empty sm:col-span-2 lg:col-span-3">No active teachers found.</p>
            @endforelse
        </div>
    </section>
@else
    <section class="panel">
        <div class="panel-header">
            <div>
                <a class="link text-sm" href="{{ route('school-academic.assignments') }}">← Back to teachers</a>
                <h2 id="assignments" class="panel-title mt-1 scroll-mt-20">{{ $selectedTeacher->full_name }}</h2>
                <p class="mt-1 text-sm text-slate-500">Configure this teacher's subject assignments.</p>
            </div>
            <a class="btn btn-primary btn-small" href="{{ route('school-academic.assignments.create', ['teacher_id' => $selectedTeacher->id]) }}">Add subject</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Subject</th><th>Class</th><th>Weekly workload</th><th>Actions</th></tr></thead>
                <tbody>
                    @php($assignmentGroups = $assignments->groupBy(fn ($assignment) => $assignment->teacher_id.'-'.$assignment->academic_year_id))
                    @forelse ($assignmentGroups as $teacherAssignments)
                        @php($weeklyLimits = $teacherAssignments->pluck('weekly_period_limit')->filter(fn ($limit) => $limit !== null)->unique()->values())
                        @php($workloadKey = $teacherAssignments->first()->teacher_id.'-'.$teacherAssignments->first()->academic_year_id)
                        @php($scheduledPeriods = $timetableCounts->get($workloadKey, 0))
                        @php($substitutionPeriods = $substitutionCounts->get($workloadKey, 0))
                        @php($totalPeriods = $scheduledPeriods + $substitutionPeriods)
                        @foreach ($teacherAssignments as $assignmentIndex => $assignment)
                            <tr>
                                <td><div>{{ $assignment->subject?->name }}</div><div class="text-xs text-slate-500">{{ $assignment->academicYear?->name }}</div></td>
                                <td>{{ $assignment->grade?->name }} &middot; {{ $assignment->section?->name ?? 'All sections' }}</td>
                                @if ($assignmentIndex === 0)
                                    <td rowspan="{{ $teacherAssignments->count() }}" class="align-top">
                                        <div><strong>Scheduled: {{ $scheduledPeriods }}</strong> / week</div>
                                        @if ($substitutionPeriods > 0)
                                            <div class="text-xs text-amber-700">Substitutions: +{{ $substitutionPeriods }} this week</div>
                                        @endif
                                        <div class="text-xs text-slate-500">Total workload: {{ $totalPeriods }} / week</div>
                                        @if ($weeklyLimits->count() === 1)
                                            <div class="text-xs text-slate-500">Maximum: {{ $weeklyLimits->first() }} / week</div>
                                            @if ($totalPeriods > (int) $weeklyLimits->first())
                                                <div class="mt-1 text-xs font-semibold text-rose-700">Over limit by {{ $totalPeriods - (int) $weeklyLimits->first() }}</div>
                                            @endif
                                        @elseif ($weeklyLimits->isNotEmpty())
                                            <div class="text-xs text-slate-500">Maximum varies: {{ $weeklyLimits->implode(', ') }}</div>
                                        @else
                                            <div class="text-xs text-slate-500">No maximum set</div>
                                        @endif
                                    </td>
                                @endif
                                <td class="align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <details>
                                            <summary class="btn btn-secondary btn-small cursor-pointer list-none">Edit assignment</summary>
                                            <form method="POST" action="{{ route('school-academic.assignments.update', $assignment) }}" class="mt-3 min-w-64 space-y-2 rounded-md border border-slate-200 bg-slate-50 p-3">
                                                @csrf
                                                @method('PUT')
                                                <label class="form-label">Subject<select name="subject_id">
                                                    @foreach ($subjects as $subject)
                                                        <option value="{{ $subject->id }}" @selected($assignment->subject_id === $subject->id)>{{ $subject->name }}</option>
                                                    @endforeach
                                                </select></label>
                                                <label class="form-label">Grade<select name="grade_id">
                                                    @foreach ($grades as $grade)
                                                        <option value="{{ $grade->id }}" @selected($assignment->grade_id === $grade->id)>{{ $grade->name }}</option>
                                                    @endforeach
                                                </select></label>
                                                <label class="form-label">Section<select name="section_id">
                                                    <option value="">All sections</option>
                                                    @foreach ($grades as $grade)
                                                        @foreach ($grade->sections as $section)
                                                            <option value="{{ $section->id }}" @selected($assignment->section_id === $section->id)>{{ $grade->name }} &middot; {{ $section->name }}</option>
                                                        @endforeach
                                                    @endforeach
                                                </select></label>
                                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($assignment->is_active)> Active</label>
                                                <button class="btn btn-primary btn-small w-full">Save changes</button>
                                            </form>
                                        </details>
                                        <form method="POST" action="{{ route('school-academic.assignments.destroy', $assignment) }}" onsubmit="return confirm('Delete this teacher subject assignment?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-small" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="4" class="empty">No subject assignments yet. Add the first subject for this teacher.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif
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
@endif

@if ($activeSection === 'subjects')
<section class="panel mt-6">
    <div class="panel-header"><div><h2 class="panel-title">Edit subjects</h2><p class="text-sm text-slate-500">Open a subject below to correct its name, code, description, or status.</p></div></div>
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
                    <summary class="flex cursor-pointer items-center justify-between gap-3 font-medium"><span>{{ $assignment->teacher?->full_name }} &middot; {{ $assignment->subject?->name }} &middot; {{ $assignment->grade?->name }} {{ $assignment->section?->name }}</span><span class="text-xs font-semibold uppercase tracking-wide text-teal-700">Edit assignment</span></summary>
                    <form method="POST" action="{{ route('school-academic.assignments.update', $assignment) }}" class="mt-3 grid gap-2">
                        @csrf
                        @method('PUT')
                        <label class="form-label">Academic year<select name="academic_year_id">
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" @selected($assignment->academic_year_id === $year->id)>{{ $year->name }}</option>
                            @endforeach
                        </select></label>
                        <label class="form-label">Teacher<select name="teacher_id">
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected($assignment->teacher_id === $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select></label>
                        <label class="form-label">Subject<select name="subject_id">
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected($assignment->subject_id === $subject->id)>{{ $subject->name }}</option>
                            @endforeach
                        </select></label>
                        <label class="form-label">Grade<select name="grade_id">
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}" @selected($assignment->grade_id === $grade->id)>{{ $grade->name }}</option>
                            @endforeach
                        </select></label>
                        <label class="form-label">Section<select name="section_id">
                            <option value="">All sections</option>
                            @foreach ($grades as $grade)
                                @foreach ($grade->sections as $section)
                                    <option value="{{ $section->id }}" @selected($assignment->section_id === $section->id)>{{ $grade->name }} &middot; {{ $section->name }}</option>
                                @endforeach
                            @endforeach
                        </select></label>
                        <label><span class="form-label">Teacher maximum periods per week</span><input type="number" min="1" name="weekly_period_limit" value="{{ $assignment->weekly_period_limit }}"></label>
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
