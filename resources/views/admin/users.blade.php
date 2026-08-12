@extends('layouts.app', ['title' => 'User management'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">User management</h1>
        <p class="page-subtitle">Create employee accounts, assign their role, and update user details safely.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="{{ route('branches.index') }}">Branches</a>
        <a class="btn btn-secondary" href="{{ route('admin.roles') }}">Roles</a>
        <a class="btn btn-secondary" href="{{ route('admin.audit') }}">Audit log</a>
    </div>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(18rem,1fr)_minmax(0,1.8fr)]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Add employee or user</h2></div>
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-3 p-5" data-submit-lock>
            @csrf
            <div>
                <label class="form-label">Full name *</label>
                <input name="name" required value="{{ old('name') }}">
            </div>
            <div>
                <label class="form-label">Email *</label>
                <input type="email" name="email" required value="{{ old('email') }}">
            </div>
            <div>
                <label class="form-label">WhatsApp number</label>
                <input name="whatsapp_number" value="{{ old('whatsapp_number') }}" placeholder="+961XXXXXXXX">
                <p class="mt-1 text-xs text-slate-500">Optional. Use international format for WhatsApp password reset codes.</p>
            </div>
            <div>
                <label class="form-label">Password *</label>
                <input type="password" name="password" required placeholder="At least 12 characters">
            </div>
            <div>
                <label class="form-label">Confirm password *</label>
                <input type="password" name="password_confirmation" required>
            </div>
            <div>
                <label class="form-label">Role *</label>
                <select name="role_id" required>
                    <option value="">Select role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Branch access</label>
                <select name="branch_ids[]" multiple size="4">
                    @foreach (\App\Models\Branch::where('is_active', true)->orderBy('name')->get() as $branch)
                        <option value="{{ $branch->id }}" @selected($branch->id === app(\App\Services\BranchContext::class)->id())>{{ $branch->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">New users are assigned to the current branch by default. Edit the account to choose multiple branches or a different default.</p>
            </div>
            <button class="btn btn-primary w-full">Create user</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Accounts</h2></div>
        <div class="table-wrap">
            <table class="data-table min-w-[680px]">
                <thead>
                    <tr><th>User</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }}<div class="text-xs muted">{{ $user->email }}</div><div class="text-xs muted">{{ $user->whatsapp_number }}</div></td>
                            <td>{{ $user->roles->pluck('label')->join(', ') ?: '-' }}<div class="mt-1 text-xs muted">{{ $user->branches->pluck('name')->join(', ') ?: 'No branch' }}</div></td>
                            <td>{{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }}</td>
                            <td><span class="badge {{ $user->is_active ? 'badge-green' : 'badge-red' }}">{{ $user->is_active ? 'Active' : 'Disabled' }}</span></td>
                            <td class="space-x-3">
                                <a class="link text-xs" href="{{ route('admin.users.edit', $user) }}">Edit</a>
                                @if (! $user->is(auth()->user()))
                                    <form class="inline" method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                        @csrf
                                        <button class="link text-xs">{{ $user->is_active ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <form class="inline" method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete {{ addslashes($user->name) }}? This account can be restored from the recycle bin.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-medium text-rose-700 hover:text-rose-900 hover:underline">Delete</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">Current user</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-3">{{ $users->links() }}</div>
    </section>
</div>
@endsection
