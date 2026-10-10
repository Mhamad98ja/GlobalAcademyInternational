@extends('layouts.app', ['title' => 'Academic references'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Academic references</h1>
        <p class="page-subtitle">Manage academic years, levels, grades and sections from Administration &amp; Operations. These references are used throughout student records, classes, timetables, and report cards.</p>
    </div>
</div>

<div class="grid gap-6 xl:grid-cols-3">
    <section class="panel">
        <div class="panel-header"><h2 id="academic-years" class="panel-title scroll-mt-20">Academic years</h2></div>
        <form method="POST" action="{{ route('academic.years.store') }}" class="space-y-3 border-b border-slate-200 p-5" data-submit-lock>
            @csrf
            <input name="name" required placeholder="e.g. 2027-2028">
            <div class="grid grid-cols-2 gap-2"><input type="date" name="starts_on" required><input type="date" name="ends_on" required></div>
            <select name="status"><option value="draft">Draft</option><option value="open">Open</option><option value="closed">Closed</option></select>
            <p class="text-xs text-slate-500">At year end, edit the current year to <strong>Closed</strong>, then add the next year as Draft or Open.</p>
            <button class="btn btn-primary w-full">Add academic year</button>
        </form>

        <div class="divide-y divide-slate-100">
            @forelse ($years as $year)
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div><div class="font-medium">{{ $year->name }}</div><div class="text-xs muted">{{ $year->starts_on->format('d M Y') }} - {{ $year->ends_on->format('d M Y') }}</div></div>
                        <span class="badge {{ $year->status === 'open' ? 'badge-green' : ($year->status === 'closed' ? 'badge-blue' : 'badge-amber') }}">{{ ucfirst($year->status) }}</span>
                    </div>
                    <details class="mt-3">
                        <summary class="link cursor-pointer text-xs">Edit academic year</summary>
                        <form method="POST" action="{{ route('academic.years.update', $year) }}" class="mt-3 space-y-2 rounded-md bg-slate-50 p-3" data-submit-lock>
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $year->name }}" required aria-label="Academic year name">
                            <div class="grid grid-cols-2 gap-2"><input type="date" name="starts_on" value="{{ $year->starts_on->format('Y-m-d') }}" required aria-label="Start date"><input type="date" name="ends_on" value="{{ $year->ends_on->format('Y-m-d') }}" required aria-label="End date"></div>
                            <select name="status" aria-label="Academic year status"><option value="draft" @selected($year->status === 'draft')>Draft</option><option value="open" @selected($year->status === 'open')>Open</option><option value="closed" @selected($year->status === 'closed')>Closed</option></select>
                            <button class="btn btn-secondary btn-small w-full">Save year changes</button>
                        </form>
                    </details>
                </div>
            @empty
                <div class="empty">No academic year yet.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 id="levels-grades" class="panel-title scroll-mt-20">Levels and grades</h2></div>
        <form method="POST" action="{{ route('academic.levels.store') }}" class="flex gap-2 border-b border-slate-200 p-5" data-submit-lock>
            @csrf
            <input name="name" required placeholder="New level">
            <input type="number" name="sort_order" value="0" class="w-20" aria-label="Level order">
            <button class="btn btn-secondary">Add</button>
        </form>
        <form method="POST" action="{{ route('academic.grades.store') }}" class="space-y-2 border-b border-slate-200 p-5" data-submit-lock>
            @csrf
            <select name="school_level_id"><option value="">No level</option>@foreach ($levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select>
            <div class="flex gap-2"><input name="name" required placeholder="New grade"><button class="btn btn-secondary">Add</button></div>
            <input type="number" name="sort_order" value="0" class="w-20" aria-label="Grade order">
        </form>

        <div class="divide-y divide-slate-100">
            @forelse ($levels as $level)
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3"><div class="font-semibold">{{ $level->name }}</div><span class="text-xs muted">Order {{ $level->sort_order }}</span></div>
                    <details class="mt-2">
                        <summary class="link cursor-pointer text-xs">Edit level</summary>
                        <form method="POST" action="{{ route('academic.levels.update', $level) }}" class="mt-2 flex gap-2 rounded-md bg-slate-50 p-3" data-submit-lock>
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $level->name }}" required aria-label="Level name">
                            <input type="number" name="sort_order" value="{{ $level->sort_order }}" min="0" class="w-20" aria-label="Level order">
                            <button class="btn btn-secondary btn-small">Save</button>
                        </form>
                    </details>

                    <div class="mt-3 space-y-2 border-l border-slate-200 pl-3">
                        @forelse ($level->grades as $grade)
                            <div class="rounded-md bg-slate-50 px-3 py-2">
                                <div class="flex items-center justify-between gap-3"><span class="text-sm font-medium text-slate-700">{{ $grade->name }}</span><span class="text-xs muted">{{ $grade->sections->count() }} sections</span></div>
                                <details class="mt-1">
                                    <summary class="link cursor-pointer text-xs">Edit grade</summary>
                                    <form method="POST" action="{{ route('academic.grades.update', $grade) }}" class="mt-2 space-y-2" data-submit-lock>
                                        @csrf
                                        @method('PUT')
                                        <select name="school_level_id" aria-label="Grade level"><option value="">No level</option>@foreach ($levels as $availableLevel)<option value="{{ $availableLevel->id }}" @selected($availableLevel->id === $grade->school_level_id)>{{ $availableLevel->name }}</option>@endforeach</select>
                                        <div class="flex gap-2"><input name="name" value="{{ $grade->name }}" required aria-label="Grade name"><input type="number" name="sort_order" value="{{ $grade->sort_order }}" min="0" class="w-20" aria-label="Grade order"><button class="btn btn-secondary btn-small">Save</button></div>
                                    </form>
                                </details>
                            </div>
                        @empty
                            <div class="text-sm muted">No grades in this level.</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="empty">Add a level and grade to get started.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 id="sections" class="panel-title scroll-mt-20">Sections</h2></div>
        <form method="POST" action="{{ route('academic.sections.store') }}" class="space-y-3 border-b border-slate-200 p-5" data-submit-lock>
            @csrf
            <select name="grade_id" required>
                <option value="">Select grade</option>
                @foreach ($levels as $level)
                    @foreach ($level->grades as $grade)
                        <option value="{{ $grade->id }}">{{ $level->name }} - {{ $grade->name }}</option>
                    @endforeach
                @endforeach
            </select>
            <input name="name" required placeholder="Section name, e.g. A">
            <input name="room" placeholder="Classroom">
            <button class="btn btn-primary w-full">Add section</button>
        </form>
        <div class="divide-y divide-slate-100">
            @forelse ($levels->flatMap(fn ($level) => $level->grades)->flatMap(fn ($grade) => $grade->sections) as $section)
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3"><span class="font-medium">{{ $section->grade->name }}.{{ $section->name }}</span><span class="text-xs muted">{{ $section->room ?: 'No classroom' }}</span></div>
                    <details class="mt-2">
                        <summary class="link cursor-pointer text-xs">Edit section</summary>
                        <form method="POST" action="{{ route('academic.sections.update', $section) }}" class="mt-2 space-y-2 rounded-md bg-slate-50 p-3" data-submit-lock>
                            @csrf
                            @method('PUT')
                            <select name="grade_id" required aria-label="Section grade">
                                @foreach ($levels as $level)
                                    @foreach ($level->grades as $grade)
                                        <option value="{{ $grade->id }}" @selected($grade->id === $section->grade_id)>{{ $level->name }} - {{ $grade->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <div class="grid grid-cols-2 gap-2"><input name="name" value="{{ $section->name }}" required aria-label="Section name"><input name="room" value="{{ $section->room }}" placeholder="Classroom" aria-label="Classroom"></div>
                            <button class="btn btn-secondary btn-small w-full">Save section changes</button>
                        </form>
                    </details>
                </div>
            @empty
                <div class="empty">No sections yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
