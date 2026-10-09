<section class="dars-link-panel" aria-labelledby="report-card-access-title">
    <h1 id="report-card-access-title">Users &amp; Access</h1>
    <nav aria-label="Report Card access tools">
        @if(auth()->user()->hasPermission('admin.manage'))
            <a href="{{ route('admin.users') }}"><x-nav-icon name="reports" /><span>Users</span></a>
            @if(auth()->user()->isControllerAdministrator())<a href="{{ route('admin.roles') }}"><x-nav-icon name="reports" /><span>Users Access</span></a>@endif
        @endif
        @if(auth()->user()->hasPermission('academic_year.manage'))
            <a href="{{ route('school-academic.index') }}#assignments"><x-nav-icon name="reports" /><span>Class subject's teachers</span></a>
        @endif
    </nav>
</section>
