@extends('layouts.app', ['title' => $title])

@section('content')
@if(!in_array($workspace, ['students', 'academic'], true))
<div class="page-header">
    <div>
        <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[.1em] text-slate-500"><a class="link" href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">/</span><span>{{ $title }}</span></div>
        <h1 class="page-title">{{ $title }}</h1>
        <p class="page-subtitle">{{ $description }}</p>
    </div>
    @if ($activeAcademicYear)
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><span class="font-semibold">{{ $workspace === 'students' ? 'Selected academic year:' : 'Active academic year:' }}</span> {{ $activeAcademicYear->name }}</div>
    @endif
</div>
@endif

@if(isset($studentOverview))@include('students.dossier.dashboard')@endif
@if($workspace === 'academic')@include('workspaces.report-card')@endif

@if($workspace === 'students' && $tasks->isNotEmpty())
<section class="mt-6" aria-label="Quick access">
    <div class="mb-3"><h2 class="text-lg font-semibold text-slate-900">Quick access</h2><p class="mt-1 text-sm text-slate-500">Choose where to continue. Each option opens one clear work area.</p></div>
    <div class="workspace-task-grid">
        @foreach ($tasks as $task)
            <a class="workspace-task" href="{{ route($task['route'], $task['params'] ?? []) }}">
                <span class="workspace-task-icon" aria-hidden="true"><x-nav-icon :name="$task['icon']" /></span>
                <span class="min-w-0"><span class="workspace-task-title">{{ $task['title'] }}</span><span class="workspace-task-description">{{ $task['description'] }}</span><span class="workspace-task-action">{{ $task['action'] }} <span aria-hidden="true">→</span></span></span>
            </a>
        @endforeach
    </div>
</section>
@endif

@if(!in_array($workspace, ['students', 'academic'], true))
<section class="workspace-hero workspace-hero-{{ $tone }}">
    <span class="workspace-hero-icon" aria-hidden="true"><x-nav-icon :name="$icon" /></span>
    <div>
        <p class="workspace-kicker">Operational workspace</p>
        <h2>{{ $title }}</h2>
        <p>Choose the activity you need to complete. Each area opens the existing Global Academy International records and actions for your role.</p>
    </div>
</section>

@if ($stats->isNotEmpty())
    <section class="workspace-stat-grid" aria-label="{{ $title }} summary">
        @foreach ($stats as $stat)
            <article class="workspace-stat"><p>{{ $stat['label'] }}</p><strong>{{ number_format($stat['value']) }}</strong><span>{{ $stat['note'] }}</span></article>
        @endforeach
    </section>
@endif

<section class="mt-6">
    <div class="mb-3"><h2 class="text-lg font-semibold text-slate-900">Work areas</h2><p class="mt-1 text-sm text-slate-500">Open a task to continue the work already managed by Global Academy International.</p></div>
    <div class="workspace-task-grid">
        @forelse ($tasks as $task)
            <a class="workspace-task" href="{{ route($task['route'], $task['params'] ?? []) }}">
                <span class="workspace-task-icon" aria-hidden="true"><x-nav-icon :name="$task['icon']" /></span>
                <span class="min-w-0"><span class="workspace-task-title">{{ $task['title'] }}</span><span class="workspace-task-description">{{ $task['description'] }}</span><span class="workspace-task-action">{{ $task['action'] }} <span aria-hidden="true">→</span></span></span>
            </a>
        @empty
            <div class="panel empty">Your role does not currently have a task in this workspace.</div>
        @endforelse
    </div>
</section>

@if ($activities->isNotEmpty())
    <section class="panel mt-6">
        <div class="panel-header"><div><h2 class="panel-title">Current activity</h2><p class="mt-0.5 text-xs text-slate-500">Live counts from the existing Global Academy International work records.</p></div></div>
        <div class="workspace-activity-list">
            @foreach ($activities as $activity)
                <a class="workspace-activity" href="{{ route($activity['route'], $activity['params'] ?? []) }}">
                    <span class="workspace-activity-icon" aria-hidden="true"><x-nav-icon :name="$activity['icon']" /></span>
                    <span><span class="workspace-activity-title">{{ $activity['title'] }}</span><span class="workspace-activity-description">{{ $activity['description'] }}</span></span>
                    <span class="workspace-activity-arrow" aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
@endif
@endsection
