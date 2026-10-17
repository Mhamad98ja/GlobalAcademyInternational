@extends('layouts.app', ['title' => 'Collaboration dashboard'])

@section('content')
<div class="page-header">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Collaboration & class life</p>
        <h1 class="page-title mt-1">Today’s teaching workspace</h1>
        <p class="page-subtitle">A focused starting point for the active academic year: lessons, class work, evaluations, attendance, and coverage.</p>
    </div>
    <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        <div class="font-semibold">{{ today()->format('l, d M Y') }}</div>
        <div class="mt-1 text-xs">{{ $activeAcademicYear?->name ?? 'No active academic year' }} · {{ $activeAcademicTerm?->name ?? 'No active term' }}</div>
    </div>
</div>

<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <article class="dashboard-stat-card"><span class="dashboard-stat-icon bg-sky-100 text-sky-700">{{ $todayLessons->count() }}</span><div><p>Today’s lessons</p><strong>{{ $todayLessons->count() }}</strong><small>{{ $isTeacherAgenda ? 'Your assigned periods' : 'Scheduled across your workspace' }}</small></div></article>
    <article class="dashboard-stat-card"><span class="dashboard-stat-icon bg-amber-100 text-amber-700">{{ $todaySubstitutions->whereIn('status', ['pending', 'recommended'])->count() }}</span><div><p>Coverage needing action</p><strong>{{ $todaySubstitutions->whereIn('status', ['pending', 'recommended'])->count() }}</strong><small>Pending or recommended substitutions today</small></div></article>
    <article class="dashboard-stat-card"><span class="dashboard-stat-icon bg-violet-100 text-violet-700">{{ $contents->count() }}</span><div><p>Class work</p><strong>{{ $contents->count() }}</strong><small>Agenda and resources in the current view</small></div></article>
    <article class="dashboard-stat-card"><span class="dashboard-stat-icon bg-emerald-100 text-emerald-700">{{ $announcements->count() }}</span><div><p>School updates</p><strong>{{ $announcements->count() }}</strong><small>Published notices available to you</small></div></article>
</section>

<section class="mt-6">
    <div class="mb-3 flex items-end justify-between gap-3"><div><h2 class="panel-title">What do you need to do?</h2><p class="mt-1 text-sm text-slate-500">Open the task that matches your role instead of searching through unrelated pages.</p></div></div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @forelse($dashboardTasks as $task)
            <a href="{{ route($task['route'], $task['params']) }}" class="dashboard-task-card dashboard-task-{{ $task['tone'] }}"><span>{{ $task['label'] }}</span><small>{{ $task['description'] }}</small><strong>Open workspace →</strong></a>
        @empty
            <div class="panel p-5 text-sm text-slate-500">No collaboration tasks are available for your permissions.</div>
        @endforelse
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
    <div class="panel overflow-hidden">
        <div class="panel-header"><div><h2 class="panel-title">Today’s timetable</h2><p class="mt-1 text-xs text-slate-500">{{ $isTeacherAgenda ? 'Your lessons for today, in period order.' : 'Lessons available in the current collaboration scope.' }}</p></div><a class="link text-sm" href="{{ route('timetable.index') }}">Open timetable</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Period</th><th>Subject</th><th>Class</th><th>Teacher</th></tr></thead><tbody>
            @forelse($todayLessons as $lesson)
                <tr><td><span class="badge badge-blue">Period {{ $lesson->period_number }}</span></td><td class="font-medium">{{ $lesson->subject?->name }}</td><td>{{ $lesson->grade?->name }}{{ $lesson->section ? ' · '.$lesson->section->name : '' }}</td><td>{{ $lesson->teacher?->full_name }}</td></tr>
            @empty
                <tr><td colspan="4" class="empty">No lessons are scheduled for today in your current scope.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>

    <div class="space-y-6">
        <div class="panel overflow-hidden">
            <div class="panel-header"><div><h2 class="panel-title">Coverage board</h2><p class="mt-1 text-xs text-slate-500">Absences and temporary cover periods for today.</p></div><a class="link text-sm" href="{{ route('substitutions.index') }}">Manage</a></div>
            <div class="divide-y divide-slate-100">
                @forelse($todaySubstitutions as $substitution)
                    <div class="p-4"><div class="flex items-start justify-between gap-3"><strong class="text-sm">{{ $substitution->entry?->grade?->name }}{{ $substitution->entry?->section ? ' · '.$substitution->entry->section->name : '' }}</strong><span class="badge {{ $substitution->status === 'assigned' ? 'badge-green' : 'badge-amber' }}">{{ str($substitution->status)->headline() }}</span></div><p class="mt-1 text-xs text-slate-500">Period {{ $substitution->entry?->period_number }} · {{ $substitution->entry?->subject?->name }} · absent: {{ $substitution->absentTeacher?->full_name }}</p><p class="mt-1 text-xs text-slate-700">Cover: {{ $substitution->substituteTeacher?->full_name ?? 'Not assigned' }}</p></div>
                @empty
                    <p class="empty">No substitution coverage is waiting today.</p>
                @endforelse
            </div>
        </div>
        <div class="panel overflow-hidden">
            <div class="panel-header"><div><h2 class="panel-title">Latest school updates</h2><p class="mt-1 text-xs text-slate-500">Announcements currently visible to you.</p></div></div>
            <div class="divide-y divide-slate-100">
                @forelse($announcements->take(4) as $announcement)
                    <article class="p-4"><div class="flex items-center justify-between gap-2"><strong class="text-sm">{{ $announcement->title }}</strong><span class="text-xs text-slate-500">{{ $announcement->published_at?->format('d M') }}</span></div><p class="mt-1 text-sm text-slate-600">{{ str($announcement->body)->limit(130) }}</p></article>
                @empty
                    <p class="empty">No published announcements are available.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
