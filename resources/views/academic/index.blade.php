@extends('layouts.app', ['title' => 'School structure'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">School structure</h1>
        <p class="page-subtitle">This catalog is read-only. @if(auth()->user()->hasPermission('students.view'))Add or modify academic references in <a class="link" href="{{ route('students.references.show', ['reference' => 'codes']) }}">References → Codes</a>.@endif</p>
    </div>
    <span class="badge badge-blue">Read-only</span>
</div>

<div class="grid gap-6 xl:grid-cols-2">
    <section class="panel">
        <div class="panel-header"><h2 id="academic-years" class="panel-title scroll-mt-20">Academic years</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse ($years as $year)
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <div><div class="font-medium">{{ $year->name }}</div><div class="text-xs muted">{{ $year->starts_on->format('d M Y') }} – {{ $year->ends_on->format('d M Y') }}</div></div>
                    <span class="badge {{ $year->status === 'open' ? 'badge-green' : 'badge-blue' }}">{{ ucfirst($year->status) }}</span>
                </div>
            @empty
                <div class="empty">No academic years configured.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 id="terms-semesters" class="panel-title scroll-mt-20">Terms / semesters</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse ($terms as $term)
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <div><div class="font-medium">{{ $term->name }}</div><div class="text-xs muted">{{ $term->academicYear?->name }} · {{ str($term->type)->headline() }} · {{ $term->starts_on->format('d M Y') }} – {{ $term->ends_on->format('d M Y') }}</div></div>
                    <span class="badge badge-blue">{{ str($term->status)->headline() }}</span>
                </div>
            @empty
                <div class="empty">No terms configured.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 id="levels-grades" class="panel-title scroll-mt-20">Levels and grades</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse ($levels as $level)
                <div class="px-5 py-4">
                    <h3 class="font-semibold">{{ $level->name }}</h3>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($level->grades as $grade)
                            <div class="flex justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 text-sm"><span>{{ $grade->name }}</span><span class="muted">{{ $grade->sections->count() }} sections</span></div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="empty">No levels configured.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 id="sections" class="panel-title scroll-mt-20">Sections</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse ($levels->flatMap(fn ($level) => $level->grades) as $grade)
                <div class="flex flex-wrap items-center gap-2 px-5 py-3"><span class="min-w-24 font-medium">{{ $grade->name }}</span>
                    @foreach ($grade->sections as $section)
                        <span class="badge badge-blue">{{ $section->name }}</span>
                    @endforeach
                </div>
            @empty
                <div class="empty">No sections configured.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
