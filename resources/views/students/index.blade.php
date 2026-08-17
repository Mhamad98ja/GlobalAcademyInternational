@extends('layouts.app', ['title' => 'Students'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Students</h1>
        <p class="page-subtitle">Profiles and academic-year registrations.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('students.create') }}">Add student</a>
</div>

<section class="panel">
    <div class="panel-header">
        <form method="GET" class="grid w-full gap-2 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_11rem_11rem_10rem_9rem_auto_auto]">
            <input name="search" value="{{ request('search') }}" placeholder="Search name, registration number or phone" aria-label="Search students">
            <select name="grade_id" data-grade-select data-section-target="#student-filter-section" aria-label="Filter by grade">
                <option value="">All grades</option>
                @foreach ($grades as $grade)
                    <option value="{{ $grade->id }}" @selected(request('grade_id') == $grade->id)>{{ $grade->name }}</option>
                @endforeach
            </select>
            <select id="student-filter-section" name="section_id" disabled aria-label="Filter by section">
                <option value="">All sections</option>
                @foreach ($grades as $grade)
                    @foreach ($grade->sections as $section)
                        <option value="{{ $section->id }}" data-grade-id="{{ $grade->id }}" @selected(request('section_id') == $section->id)>{{ $grade->name }}.{{ $section->name }}</option>
                    @endforeach
                @endforeach
            </select>
            <select name="registration_status" aria-label="Filter by registration status">
                <option value="">All student statuses</option>
                @foreach (['active', 'inactive', 'withdrawn', 'completed'] as $status)
                    <option value="{{ $status }}" @selected(request('registration_status') === $status)>{{ str($status)->headline() }}</option>
                @endforeach
            </select>
            <select name="sort" aria-label="Sort students by name">
                <option value="asc" @selected(request('sort', 'asc') === 'asc')>Name: A-Z</option>
                <option value="desc" @selected(request('sort') === 'desc')>Name: Z-A</option>
            </select>
            <button class="btn btn-secondary">Filter</button>
            <a class="btn btn-secondary" href="{{ route('students.index') }}">Reset</a>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Student no.</th>
                    <th>Phone</th>
                    <th>Latest registration</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <a class="link" href="{{ route('students.show', $student) }}">{{ $student->full_name }}</a>
                                @if(auth()->user()->hasPermission('distributions.create') && $student->registrations->first()?->status === 'active')
                                    <a class="link text-xs text-teal-700" href="{{ route('distributions.create', ['registration' => $student->registrations->first()->id]) }}">Distribute books</a>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500">{{ $student->registration_number ?: 'No registration number' }}</div>
                        </td>
                        <td>{{ $student->student_number }}</td>
                        <td>{{ $student->phone ?: '-' }}</td>
                        <td>{{ $student->registrations->first()?->academicYear?->name ?? 'Not registered' }}</td>
                        <td>
                            @if ($student->registrations->first())
                                <span class="badge {{ $student->registrations->first()->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ str($student->registrations->first()->status)->headline() }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="whitespace-nowrap"><a class="link text-xs" href="{{ route('students.edit', $student) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">No students matched your search.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-200 px-4 py-3"><x-pagination :paginator="$students" /></div>
</section>
@endsection
