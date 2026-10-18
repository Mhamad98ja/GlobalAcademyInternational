@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
@php
    $dashboardUser = auth()->user();
    $salutation = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $dashboardFirstName = str(preg_replace('/^(dr|mr|mrs|ms)\.?\s+/i', '', $dashboardUser->name))->before(' ');
    $quickActions = [
        ['label' => 'Find a student', 'description' => 'Open a student profile or registration record.', 'route' => 'students.index', 'permission' => 'students.view', 'icon' => 'students', 'tone' => 'blue'],
        ['label' => 'Record attendance', 'description' => 'Capture today\'s student attendance by class.', 'route' => 'attendance.students', 'permission' => 'attendance.student.view', 'icon' => 'reports', 'tone' => 'gold'],
        ['label' => 'Open evaluations', 'description' => 'Review grades for the active academic term.', 'route' => 'collaboration.index', 'params' => ['section' => 'evaluation-dashboard'], 'permission' => 'evaluation.dashboard.view', 'icon' => 'academic', 'tone' => 'mint'],
    ];
    $workspaceLinks = [
        ['label' => 'Student Profile', 'description' => 'Students, registrations, families', 'workspace' => 'students', 'permission' => 'students.view', 'icon' => 'students'],
        ['label' => 'Report Card', 'description' => 'Academic records and reports', 'workspace' => 'academic', 'permission' => 'reports.view', 'icon' => 'reports'],
        ['label' => 'Administration', 'description' => 'People, finance, and operations', 'workspace' => 'operations', 'permission' => 'admin.manage', 'icon' => 'administration'],
        ['label' => 'Online Collaboration', 'description' => 'Agenda, evaluations, and messages', 'workspace' => 'teachers', 'permission' => 'collaboration.view', 'icon' => 'responsible'],
    ];
@endphp

<section class="campus-dashboard" aria-label="Global Academy International dashboard">
    <header class="campus-welcome">
        <div>
            <p class="campus-welcome-date">{{ now()->format('l, F j, Y') }}</p>
            <h1>{{ $salutation }}, {{ $dashboardFirstName }}</h1>
            <p>Here is what is happening at Global Academy International today.</p>
        </div>
        @if($activeAcademicYear)
            <div class="campus-year-card"><span>Active academic year</span><strong>{{ $activeAcademicYear->name }}</strong></div>
        @endif
    </header>

    <nav class="campus-workspace-strip" aria-label="Core workspaces">
        @foreach($workspaceLinks as $workspaceLink)
            @if($dashboardUser->hasPermission($workspaceLink['permission']))
                <a href="{{ route('workspaces.show', $workspaceLink['workspace']) }}"><span class="campus-workspace-icon"><x-nav-icon :name="$workspaceLink['icon']" /></span><span><strong>{{ $workspaceLink['label'] }}</strong><small>{{ $workspaceLink['description'] }}</small></span><b aria-hidden="true">→</b></a>
            @endif
        @endforeach
    </nav>

    <section class="campus-action-grid" aria-label="Quick actions">
        @foreach($quickActions as $action)
            @if($dashboardUser->hasPermission($action['permission']))
                <a class="campus-action campus-action-{{ $action['tone'] }}" href="{{ route($action['route'], $action['params'] ?? []) }}">
                    <span class="campus-action-icon"><x-nav-icon :name="$action['icon']" /></span>
                    <span><strong>{{ $action['label'] }}</strong><small>{{ $action['description'] }}</small></span>
                    <span class="campus-action-arrow" aria-hidden="true">→</span>
                </a>
            @endif
        @endforeach
    </section>

    <section class="campus-stat-grid" aria-label="School summary">
        <article class="campus-stat"><span class="campus-stat-icon campus-stat-icon-blue"><x-nav-icon name="students" /></span><span><small>Active students</small><strong>{{ number_format($activeStudents) }}</strong><em>Current registrations</em></span></article>
        <article class="campus-stat"><span class="campus-stat-icon campus-stat-icon-gold"><x-nav-icon name="books" /></span><span><small>Books in stock</small><strong>{{ number_format($stock) }}</strong><em>{{ number_format($lowStock->count()) }} need attention</em></span></article>
        <article class="campus-stat"><span class="campus-stat-icon campus-stat-icon-mint"><x-nav-icon name="distribution" /></span><span><small>Today's distributions</small><strong>{{ number_format($todayDistributions) }}</strong><em>Recorded in this branch</em></span></article>
        @if($canViewFinancials)
            <article class="campus-stat"><span class="campus-stat-icon campus-stat-icon-sky"><x-nav-icon name="payments" /></span><span><small>Collected today</small><strong>{{ number_format((float) ($collected ?? 0), 2) }}</strong><em>{{ number_format((float) ($outstanding ?? 0), 2) }} outstanding</em></span></article>
        @endif
    </section>

    <div class="campus-content-grid">
        <section class="campus-panel campus-panel-large">
            <header class="campus-panel-header"><div><h2>Stock and operations</h2><p>Items that may need attention in the current school year.</p></div>@if($dashboardUser->hasPermission('operations.view'))<a href="{{ route('operations.index') }}" class="campus-panel-link">Open operations</a>@endif</header>
            <div class="campus-list">
                @forelse($lowStock as $book)
                    <div class="campus-list-row"><span class="campus-list-marker campus-list-marker-gold"></span><span><strong>{{ $book->title }}</strong><small>{{ $book->branch_quantity }} available · minimum {{ $book->minimum_stock }}</small></span><span class="campus-row-status">Low stock</span></div>
                @empty
                    <div class="campus-empty"><strong>Everything is stocked</strong><span>No low-stock books are currently flagged.</span></div>
                @endforelse
            </div>
        </section>

        <section class="campus-panel">
            <header class="campus-panel-header"><div><h2>Recent activity</h2><p>Latest recorded school operations.</p></div></header>
            <div class="campus-list">
                @forelse($recentActivity as $activity)
                    <div class="campus-list-row"><span class="campus-list-marker campus-list-marker-blue"></span><span><strong>{{ $activity->book?->title ?? 'Inventory activity' }}</strong><small>{{ str($activity->type)->headline() }} · {{ $activity->user?->name ?? 'System' }}</small></span><time>{{ $activity->created_at?->format('d M') }}</time></div>
                @empty
                    <div class="campus-empty"><strong>No recent activity</strong><span>New stock activity will appear here.</span></div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="campus-next-step">
        <div><span class="campus-next-step-icon"><x-nav-icon name="academic" /></span><span><strong>Keep the school day moving</strong><small>Use the left navigation to move between students, teaching, attendance, reports, and administration without losing your place.</small></span></div>
        @if($dashboardUser->hasPermission('reports.view'))<a href="{{ route('reports.index') }}" class="btn btn-primary">Open report centre</a>@endif
    </section>
</section>
@endsection
