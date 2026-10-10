@extends('layouts.app', ['title' => 'Manage access'])

@section('content')
<div class="page-header">
    <div>
        <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[.1em] text-slate-500">
            <a class="link" href="{{ route('admin.users') }}">Users and Access</a><span aria-hidden="true">/</span><span>Manage access</span>
        </div>
        <h1 class="page-title">Manage access</h1>
        <p class="page-subtitle">Choose the permissions available to <strong>{{ $managedUser->name }}</strong>. Checked permissions are active.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('admin.users') }}">Back to users</a>
</div>

<form method="POST" action="{{ route('admin.users.access.update', $managedUser) }}" class="user-access-page" data-submit-lock>
    @csrf
    @method('PUT')
    <section class="panel user-access-summary">
        <div>
            <span class="user-access-avatar">{{ str($managedUser->name)->trim()->substr(0, 1)->upper() }}</span>
            <div><h2>{{ $managedUser->name }}</h2><p>{{ $managedUser->email }}</p></div>
        </div>
        <div class="user-access-roles"><span>Role{{ $managedUser->roles->count() === 1 ? '' : 's' }}</span><strong>{{ $managedUser->roles->pluck('label')->join(', ') ?: 'No role assigned' }}</strong></div>
    </section>

    <section class="panel mt-5">
        <div class="panel-header">
            <div><h2 class="panel-title">Permissions</h2><p class="mt-1 text-sm text-slate-500">Role permissions are shown as inherited. A saved checkbox selection becomes an explicit access decision for this user.</p></div>
            <span class="badge badge-blue">{{ $effectivePermissionIds->count() }} active</span>
        </div>
        <div class="user-access-grid">
            @foreach($permissions->groupBy(fn ($permission) => str($permission->name)->before('.')->headline()) as $group => $groupPermissions)
                <fieldset class="user-access-group">
                    <legend>{{ $group }}</legend>
                    @foreach($groupPermissions as $permission)
                        @php($direct = $directPermissions->get($permission->id))
                        @php($inherited = $rolePermissionIds->contains((int) $permission->id))
                        <label class="user-access-permission">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($effectivePermissionIds->contains((int) $permission->id))>
                            <span><strong>{{ $permission->label }}</strong><small>{{ $permission->name }}</small></span>
                            @if($direct)<em class="{{ $direct->pivot->allowed ? 'is-explicit' : 'is-blocked' }}">{{ $direct->pivot->allowed ? 'Explicit' : 'Blocked' }}</em>@elseif($inherited)<em>Inherited</em>@endif
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
        </div>
        <div class="user-access-footer"><p>Changes apply immediately after saving. The Controller Administrator keeps full system access.</p><button class="btn btn-primary">Save access</button></div>
    </section>
</form>
@endsection
