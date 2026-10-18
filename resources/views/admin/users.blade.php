@extends('layouts.app', ['title' => 'Users and Access'])

@section('content')
@php($personType = $filters['person_type'] ?? 'all')
@php($showUsers = $personType !== 'parents')
@php($showParents = in_array($personType, ['all', 'parents'], true))
@php($selectedBranch = $branches->firstWhere('id', $filters['branch_id'] ?? $currentBranchId))

<section class="dars-admin-users">
    <div class="dars-admin-toolbar no-print">
        <button type="button" class="dars-action-button" data-modal="add-user-modal"><span aria-hidden="true">+</span> Add user</button>
        <a class="dars-action-button" href="{{ route('responsible.index') }}"><span aria-hidden="true">+</span> Add parent / guardian</a>
    </div>

    <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 no-print">
        Student accounts are created only from a parent or guardian profile. Open <a class="font-semibold underline" href="{{ route('responsible.index') }}">Parents &amp; guardians</a> to create the family and its student portal accounts together.
    </div>

    <form method="GET" class="dars-admin-filters no-print">
        <label><span>Directory</span><select name="person_type"><option value="all" @selected($personType === 'all')>All people</option><option value="teachers" @selected($personType === 'teachers')>Teachers</option><option value="students" @selected($personType === 'students')>Students</option><option value="parents" @selected($personType === 'parents')>Parents &amp; guardians</option><option value="staff" @selected($personType === 'staff')>Staff &amp; administrators</option></select></label>
        <label><span>Status</span><select name="status"><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="disabled" @selected(($filters['status'] ?? '') === 'disabled')>Disabled</option><option value="all" @selected(($filters['status'] ?? '') === 'all')>All</option></select></label>
        <label><span>Account role</span><select name="role_id"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(($filters['role_id'] ?? null) == $role->id)>{{ $role->label }}</option>@endforeach</select></label>
        @if($canManageBranchAssignments)
            <label><span>Branch</span><select name="branch_id"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? null) == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>@endforeach</select></label>
        @else
            <div class="flex items-end pb-2 text-sm text-slate-600"><span>Branch: <strong>{{ $selectedBranch?->name ?? 'Current branch' }}</strong></span></div>
        @endif
        <label class="dars-admin-name-filter"><span>Name, email, code, or student</span><input name="q" value="{{ $filters['q'] ?? '' }}"></label>
        <button class="dars-admin-search" aria-label="Search people">Search</button>
    </form>

    @if($showUsers)
        <div class="mb-6">
            <div class="mb-2 flex items-center justify-between gap-3"><h2 class="panel-title">User accounts</h2><span class="text-xs text-slate-500">{{ $users->total() }} account(s)</span></div>
            <div class="dars-admin-table-wrap"><table class="dars-admin-table"><thead><tr><th class="avatar-column"></th><th>Display name</th><th>User type</th><th></th><th></th><th></th></tr></thead><tbody>
                @forelse($users as $user)
                    <tr>
                        <td>@if($user->profile_photo_path)<img src="{{ route('admin.users.photo', $user) }}" alt="" class="dars-user-avatar object-cover">@else<span class="dars-user-avatar" aria-hidden="true">{{ str($user->name)->trim()->substr(0, 1)->upper() }}</span>@endif</td>
                        <td><span class="dars-user-name">{{ $user->name }}</span>
                            @if(!$user->is_active)<span class="badge badge-red ml-2">Disabled</span>@endif
                            @if($user->student_id)
                                <div class="text-xs text-slate-500">Student portal account</div>
                            @elseif($user->teacher_id)
                                <div class="text-xs text-slate-500">Teacher account</div>
                            @endif
                        </td>
                        <td>{{ $user->is_controller_administrator ? 'Global Super Administrator' : ($user->hasRole('super-administrator') ? 'Branch Administrator' : ($user->roles->pluck('label')->join(', ') ?: 'User')) }}</td>
                        <td><a href="{{ route('admin.users.edit', $user) }}">Profile</a></td>
                        <td>@if(auth()->user()->isControllerAdministrator())<a class="dars-icon-button dars-access-button" href="{{ route('admin.users.access.edit', $user) }}" aria-label="Manage access for {{ $user->name }}">Manage access</a>@endif</td>
                        <td><a class="dars-icon-button dars-edit-button" href="{{ route('admin.users.edit', $user) }}" aria-label="Edit {{ $user->name }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No user accounts match these filters.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="dars-admin-pagination no-print">{{ $users->links() }}</div>
        </div>
    @endif

    @if($showParents)
        <section class="panel" @if($personType === 'parents') style="margin-top: 1.25rem" @endif>
            <div class="panel-header"><div><h2 class="panel-title">Parents &amp; guardians</h2><p class="mt-1 text-xs text-slate-500">Family records are linked to their students. They are managed here, while student portal accounts are created from the family profile.</p></div><span class="text-xs text-slate-500">{{ $parents->total() }} record(s)</span></div>
            <div class="table-wrap"><table class="data-table w-full"><thead><tr><th>Parent / guardian</th><th>Relationship</th><th>Contact</th><th>Linked students</th><th></th></tr></thead><tbody>@forelse($parents as $parent)<tr><td><strong>{{ $parent->full_name }}</strong><div class="text-xs text-slate-500">{{ $parent->parent_code ?: 'No family code' }}</div></td><td>{{ str($parent->type)->headline() }}</td><td>{{ $parent->email ?: 'No email' }}<div class="text-xs text-slate-500">{{ $parent->phone ?: 'No phone' }}</div></td><td>{{ $parent->students->pluck('full_name')->join(', ') ?: 'No students linked' }}</td><td class="whitespace-nowrap"><a class="link text-xs" href="{{ route('responsible.edit', $parent) }}">Open family profile</a></td></tr>@empty<tr><td colspan="5" class="empty">No parent or guardian records match these filters.</td></tr>@endforelse</tbody></table></div>
            <div class="border-t border-slate-200 p-3">{{ $parents->onEachSide(1)->links() }}</div>
        </section>
    @endif
</section>

<div id="add-user-modal" class="modal no-print" aria-hidden="true"><div class="modal-card max-h-[92vh] max-w-3xl overflow-y-auto"><div class="panel-header"><div><h2 class="panel-title">Add user account</h2><p class="mt-1 text-xs text-slate-500">Create a teacher, employee, or administrator account. Student accounts are created from a parent / guardian profile.</p></div><button type="button" data-close-modal aria-label="Close">&times;</button></div><form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5 p-5" data-submit-lock>@csrf
    @if($canManageBranchAssignments)
        <div><label class="form-label">Role *</label><select name="role_id" required data-user-role-select><option value="">Select role</option>@foreach($roles->reject(fn ($role) => $role->name === 'student') as $role)<option value="{{ $role->id }}" data-role-name="{{ $role->name }}" @selected(old('role_id') == $role->id)>{{ $role->name === 'super-administrator' ? 'Branch Administrator' : $role->label }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Teacher accounts must be linked to a teacher profile. Create students with their parent / guardian instead.</p></div>
    @else
        <input type="hidden" name="role_id" value="{{ $roles->firstWhere('name', 'employee')?->id }}">
    @endif
    <div class="form-grid"><div><label class="form-label">Full name *</label><input name="name" required value="{{ old('name') }}"></div><div><label class="form-label">Email *</label><input type="email" name="email" required value="{{ old('email') }}"></div><div><label class="form-label">Phone</label><input name="phone" value="{{ old('phone') }}" placeholder="Phone number"></div><div data-role-field="teacher"><label class="form-label">Linked teacher profile <span class="role-required-marker">(required for Teacher)</span></label><select name="teacher_id"><option value="">Not a teacher account</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->employee_number ? $teacher->employee_number.' · ' : '' }}{{ $teacher->full_name }}</option>@endforeach</select></div><div><label class="form-label">Password *</label><input type="password" name="password" required placeholder="At least 12 characters"></div><div><label class="form-label">Confirm password *</label><input type="password" name="password_confirmation" required></div></div>
    @if($canManageBranchAssignments)
        <section class="border-t border-slate-200 pt-4"><h2 class="panel-title">Branch</h2><p class="mt-1 text-xs text-slate-500">Choose where this person works. This becomes their starting branch automatically. You can add more branches later from their profile.</p><div class="mt-3"><label class="form-label" for="new-user-branch">Branch *</label><select id="new-user-branch" name="branch_id" required><option value="">Select branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $currentBranchId) == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>@endforeach</select></div><p class="mt-2 text-xs text-slate-500">A Branch Administrator account can be prepared here, then made responsible from the Branches page. The Global Super Administrator alone controls school-wide settings.</p></section>
    @endif
    <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close-modal>Cancel</button><button class="btn btn-primary">Create user</button></div></form></div></div>
@if($errors->any())<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('add-user-modal')?.classList.add('is-open'));</script>@endif
@endsection
