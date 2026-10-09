@extends('layouts.app', ['title' => 'School reports'])

@section('content')
@php($query = array_merge(request()->except('page'), ['report' => $report]))
<div class="page-header">
    <div><div class="text-sm text-slate-500"><a class="link" href="{{ route('reports.index') }}">Reports</a> / School operations</div><h1 class="page-title mt-1">School operations reports</h1><p class="page-subtitle">Filter academic, attendance, wellbeing, timetable, workload, substitution, and grading records from one reporting surface.</p></div>
    <div class="flex gap-2">
        <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ route('reports.school.print', $query) }}">Print / PDF</a>
        @if (auth()->user()->hasPermission('reports.export'))
            <a class="btn btn-primary" href="{{ route('reports.school.csv', $query) }}">Export Excel CSV</a>
        @endif
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="grid w-full gap-2 md:grid-cols-2 xl:grid-cols-5">
            @if(request('module') === 'students')<input type="hidden" name="module" value="students">@endif
            <select name="report" aria-label="Report type" onchange="this.form.submit()"><option value="">Report type</option>
                @foreach ($reports as $key => $label)
                    <option value="{{ $key }}" @selected($report === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @if(in_array('academic_year_id', $reportFilters, true))<div><label class="form-label">Academic year</label><select aria-label="Academic year" name="academic_year_id"><option value="">All academic years</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}</option>
                @endforeach
            </select></div>@endif
            @if(in_array('academic_term_id', $reportFilters, true))<div><label class="form-label">Term</label><select aria-label="Term" name="academic_term_id"><option value="">All terms</option>
                @foreach ($terms as $term)
                    <option value="{{ $term->id }}" @selected(($filters['academic_term_id'] ?? null) == $term->id)>{{ $term->academicYear?->name }} &middot; {{ $term->name }}</option>
                @endforeach
            </select></div>@endif
            @if(in_array('student_id', $reportFilters, true))<div><label class="form-label">Student</label><select aria-label="Student" name="student_id"><option value="">All students</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected(($filters['student_id'] ?? null) == $student->id)>{{ $student->full_name }}</option>
                @endforeach
            </select></div>@endif
            @if(in_array('teacher_id', $reportFilters, true))<div><label class="form-label">Teacher</label><select aria-label="Teacher" name="teacher_id"><option value="">All teachers</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected(($filters['teacher_id'] ?? null) == $teacher->id)>{{ $teacher->full_name }}</option>
                @endforeach
            </select></div>@endif
            @if(in_array('grade_id', $reportFilters, true))<div><label class="form-label">Grade</label><select aria-label="Grade" name="grade_id" data-grade-select data-section-target="#school-report-section"><option value="">All grades</option>
                @foreach ($grades as $grade)
                    <option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? null) == $grade->id)>{{ $grade->name }}</option>
                @endforeach
            </select></div>@endif
            @if(in_array('section_id', $reportFilters, true))<div><label class="form-label">Section</label><select aria-label="Section" id="school-report-section" name="section_id" disabled><option value="">All sections</option>
                @foreach ($grades as $grade)
                    @foreach ($grade->sections as $section)
                        <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(($filters['section_id'] ?? null) == $section->id)>{{ $grade->name }} &middot; {{ $section->name }}</option>
                    @endforeach
                @endforeach
            </select></div>@endif
            @if(in_array('subject_id', $reportFilters, true))<div><label class="form-label">Subject</label><select aria-label="Subject" name="subject_id"><option value="">All subjects</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(($filters['subject_id'] ?? null) == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select></div>@endif
            @if(in_array('date_from', $reportFilters, true))<div><label class="form-label">From date</label><input aria-label="From date" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>@endif
            @if(in_array('date_to', $reportFilters, true))<div><label class="form-label">To date</label><input aria-label="To date" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>@endif
            @if(in_array('status', $reportFilters, true))<div><label class="form-label">Status</label><input aria-label="Status" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Status"></div>@endif<button class="btn btn-secondary">Run report</button><a class="btn btn-secondary" href="{{ route('reports.school', ['report' => $report, 'module' => request('module')]) }}">Reset</a>
        </form>
    </div>
    <p class="px-5 py-3 text-sm text-slate-500">{{ $rows->count() }} records shown.@if($rows->count() === 1000) Results are limited to 1,000 records; narrow the filters for a complete print or export.@endif</p><div class="table-wrap"><table class="data-table"><thead><tr>
        @foreach ($headings as $heading)
            <th>{{ $heading }}</th>
        @endforeach
    </tr></thead><tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($row as $value)
                    <td>{{ $value }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($headings) }}" class="empty">No records match the selected filters.</td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection
