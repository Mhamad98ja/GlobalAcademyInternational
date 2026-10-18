@php
    $setupLinks = [
        ['route' => 'admin.users', 'label' => 'Users', 'description' => 'Manage student, teacher, and employee accounts.', 'action' => 'Open users', 'icon' => 'administration', 'permission' => 'admin.manage'],
        ['route' => 'school-academic.index', 'label' => 'Academic operations', 'description' => 'Manage subjects and teacher subject assignments.', 'action' => 'Open operations', 'icon' => 'academic', 'permission' => 'academic_year.manage'],
        ['route' => 'school-academic.assignments', 'label' => 'Teacher Subject Assignments', 'description' => 'Configure subjects, classes, workloads, and teacher assignments.', 'action' => 'Open assignments', 'icon' => 'teachers', 'permission' => 'academic.teacher-assignments.view'],
        ['route' => 'timetable.index', 'label' => 'Graphical timetable', 'description' => 'Build class schedules and prevent teacher conflicts.', 'action' => 'Open timetable', 'icon' => 'academic', 'permission' => 'timetable.view'],
    ];
@endphp

<section class="academic-hub" aria-labelledby="academic-hub-title">
    <div class="academic-hub-heading">
        <div>
            <p class="academic-hub-kicker">Academic workspace</p>
            <h1 id="academic-hub-title">Academic &amp; Report Card</h1>
            <p>All academic setup links in one main page.</p>
        </div>
        <span class="academic-hub-rule" aria-hidden="true"></span>
    </div>

    <nav class="academic-hub-grid" aria-label="Academic workspace links">
        @foreach ($setupLinks as $link)
            @if (auth()->user()->hasPermission($link['permission']))
                <a class="academic-hub-card" href="{{ route($link['route'], $link['params'] ?? []) }}">
                    <span class="academic-hub-card-icon"><x-nav-icon :name="$link['icon']" /></span>
                    <span><strong>{{ $link['label'] }}</strong><small>{{ $link['description'] }}</small><em>{{ $link['action'] }} &rarr;</em></span>
                </a>
            @endif
        @endforeach
    </nav>
</section>
