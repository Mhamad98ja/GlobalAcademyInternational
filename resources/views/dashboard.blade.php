@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
@php
    $dashboardUser = auth()->user();
    $salutation = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $dashboardFirstName = str(preg_replace('/^(dr|mr|mrs|ms)\\.?\\s+/i', '', $dashboardUser->name))->before(' ');
    $modules = [
        ['workspace' => 'students', 'title' => 'Student Profile', 'description' => 'Search student records, registrations, family details, attendance, and documents.', 'action' => 'Open student records', 'icon' => 'students', 'tone' => 'blue', 'visible' => $dashboardUser->hasPermission('students.view')],
        ['workspace' => 'academic', 'title' => 'Academic & Report Card', 'description' => 'Manage assessments, report cards, grades, subjects, and academic reports.', 'action' => 'Open academics', 'icon' => 'academic', 'tone' => 'indigo', 'visible' => $dashboardUser->hasPermission('grades.enter') || $dashboardUser->hasPermission('reports.view')],
        ['workspace' => 'operations', 'title' => 'Administration & Operations', 'description' => 'Handle users, books, inventory, suppliers, payments, transfers, and daily operations.', 'action' => 'Open administration', 'icon' => 'operations', 'tone' => 'amber', 'visible' => $dashboardUser->hasPermission('admin.manage') || $dashboardUser->hasPermission('books.view')],
        ['workspace' => 'teachers', 'title' => 'Online Collaboration', 'description' => 'Coordinate teachers, course work, messages, events, and student follow-up.', 'action' => 'Open collaboration', 'icon' => 'responsible', 'tone' => 'emerald', 'visible' => $dashboardUser->hasPermission('collaboration.view')],
    ];
    $quickLinks = [
        ['label' => 'Find a student', 'description' => 'Search profiles and registrations', 'route' => 'students.index', 'permission' => 'students.view', 'icon' => 'students'],
        ['label' => 'View reports', 'description' => 'Academic and operational reporting', 'route' => 'reports.index', 'permission' => 'reports.view', 'icon' => 'reports'],
        ['label' => 'Open calendar', 'description' => 'School events and key dates', 'route' => 'collaboration.index', 'params' => ['section' => 'calendar'], 'permission' => 'collaboration.view', 'icon' => 'academic'],
        ['label' => 'Manage users', 'description' => 'Roles, access, and configuration', 'route' => 'admin.users', 'permission' => 'admin.manage', 'icon' => 'administration'],
    ];
@endphp
<section class="sbm-dashboard" aria-label="Global Academy International dashboard">
    <header class="sbm-dashboard-intro">
        <div>
            <p class="sbm-dashboard-date">{{ now()->format('l, F j, Y') }}</p>
            <h1>{{ $salutation }}, {{ $dashboardFirstName }}.</h1>
            <p>Choose a workspace to continue managing your school day.</p>
        </div>
        <a class="btn btn-primary sbm-dashboard-primary" href="{{ route('students.index') }}"><x-nav-icon name="students" /> Find a student</a>
    </header>

    <div class="sbm-dashboard-layout">
        <section class="sbm-workspace-region" aria-labelledby="workspaces-title">
            <div class="sbm-section-heading"><div><p>WORKSPACES</p><h2 id="workspaces-title">Your school at a glance</h2></div><span>{{ collect($modules)->where('visible', true)->count() }} available</span></div>
            <div class="sbm-workspace-grid">
                @foreach ($modules as $module)
                    @if ($module['visible'])
                        @php($target = match ($module['workspace']) { 'teachers' => route('collaboration.index'), 'operations' => $dashboardUser->hasPermission('admin.manage') ? route('admin.users') : route('workspaces.show', 'operations'), default => route('workspaces.show', $module['workspace']) })
                        <a class="sbm-workspace-card sbm-workspace-{{ $module['tone'] }}" href="{{ $target }}">
                            <span class="sbm-workspace-icon"><x-nav-icon :name="$module['icon']" /></span>
                            <span class="sbm-workspace-content"><strong>{{ $module['title'] }}</strong><span>{{ $module['description'] }}</span><em>{{ $module['action'] }} <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 5l5 5-5 5" /></svg></em></span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>

        <aside class="sbm-dashboard-side" aria-label="Quick access">
            <section class="sbm-side-panel"><div class="sbm-section-heading"><div><p>QUICK ACCESS</p><h2>Useful shortcuts</h2></div></div><div class="sbm-quick-links">
                @foreach($quickLinks as $link)
                    @if($dashboardUser->hasPermission($link['permission']))
                        <a href="{{ route($link['route'], $link['params'] ?? []) }}"><span><x-nav-icon :name="$link['icon']" /></span><span><strong>{{ $link['label'] }}</strong><small>{{ $link['description'] }}</small></span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M7 4l6 6-6 6" /></svg></a>
                    @endif
                @endforeach
            </div></section>
            <section class="sbm-day-panel"><p>READY WHEN YOU ARE</p><h2>Keep your school moving forward.</h2><span>Global Academy International brings daily student, academic, and operational work into one focused workspace.</span></section>
        </aside>
    </div>
</section>
@endsection
