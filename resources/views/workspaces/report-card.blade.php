<section class="academic-hub" aria-labelledby="academic-hub-title">
    <div class="academic-hub-heading">
        <div>
            <p class="academic-hub-kicker">Academic workspace</p>
            <h1 id="academic-hub-title">Academic &amp; Report Card</h1>
            <p>Use one clear starting point for access management and class subject setup.</p>
        </div>
        <span class="academic-hub-rule" aria-hidden="true"></span>
    </div>
    <nav class="academic-hub-grid" aria-label="Academic workspace shortcuts">
        @if(auth()->user()->hasPermission('admin.manage'))
            <a class="academic-hub-card" href="{{ route('admin.users') }}">
                <span class="academic-hub-card-icon"><x-nav-icon name="administration" /></span>
                <span><strong>Users</strong><small>Manage student, teacher, and employee accounts.</small><em>Open users</em></span>
            </a>
            @if(auth()->user()->isControllerAdministrator())
                <a class="academic-hub-card" href="{{ route('admin.roles') }}">
                    <span class="academic-hub-card-icon"><x-nav-icon name="administration" /></span>
                    <span><strong>Users Access</strong><small>Review roles and permissions for the school.</small><em>Open access</em></span>
                </a>
            @endif
        @endif
        @if(auth()->user()->hasPermission('academic_year.manage'))
            <a class="academic-hub-card" href="{{ route('school-academic.index') }}#assignments">
                <span class="academic-hub-card-icon"><x-nav-icon name="academic" /></span>
                <span><strong>Class Subjects</strong><small>Connect each teacher, subject, grade, and section.</small><em>Open class subjects</em></span>
            </a>
        @endif
    </nav>
</section>
