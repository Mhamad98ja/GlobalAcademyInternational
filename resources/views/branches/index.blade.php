@extends('layouts.app', ['title' => 'Branches'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Branches</h1>
        <p class="page-subtitle">Create locations, assign users, and designate one Branch Super Administrator for each branch.</p>
        @if ($controllerAdministrator)<p class="mt-2 text-xs font-medium text-violet-700">Controller Administrator: {{ $controllerAdministrator->name }}. Only this account can create, edit, or deactivate branches.</p>@endif
    </div>
</div>

@php($branchAdministrators = $users->filter(fn ($user) => ! $user->is_controller_administrator && $user->hasRole('super-administrator')))
@php($newBranchUserIds = collect(old('user_ids', []))->map(fn ($id) => (int) $id)->all())
<div class="grid gap-6 xl:grid-cols-[minmax(19rem,1fr)_minmax(0,1.8fr)]">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">New branch</h2></div>
        <form method="POST" action="{{ route('branches.store') }}" class="space-y-3 p-5" data-submit-lock>
            @csrf
            <div><label class="form-label">Code *</label><input name="code" required value="{{ old('code') }}" placeholder="BEIRUT"></div>
            <div><label class="form-label">Name *</label><input name="name" required value="{{ old('name') }}"></div>
            <div><label class="form-label">Phone</label><input name="phone" value="{{ old('phone') }}"></div>
            <div><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}"></div>
            <div><label class="form-label">Address</label><textarea name="address" rows="2">{{ old('address') }}</textarea></div>
            <div><label class="form-label">Users *</label><div class="max-h-52 space-y-2 overflow-y-auto rounded-md border border-slate-300 p-3">@foreach($users as $user)<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, $newBranchUserIds, true))>{{ $user->name }} - {{ $user->email }}</label>@endforeach</div><p class="mt-1 text-xs text-slate-500">Select every user who should be able to work in this branch.</p></div>
            <div><label class="form-label">Default user (optional)</label><select name="default_user_id"><option value="">None</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
            <div><label class="form-label">Branch Super Administrator *</label><select name="branch_administrator_id" required><option value="">Select the responsible administrator</option>@foreach($branchAdministrators as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">This user must also be selected in Users above and has full control only within this branch.</p></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Active branch</label>
            <button class="btn btn-primary w-full">Create branch</button>
        </form>
    </section>

    <section class="space-y-4">
        @foreach($branches as $branch)
            @php($assignedUserIds = collect(old('user_ids', $branch->users->pluck('id')->all()))->map(fn ($id) => (int) $id)->all())
            <form method="POST" action="{{ route('branches.update', $branch) }}" class="panel" data-submit-lock>
                @csrf
                @method('PUT')
                <div class="panel-header"><div><h2 class="panel-title">{{ $branch->name }}</h2><p class="mt-1 text-xs text-slate-500">Total students: {{ number_format($branch->students_count) }} · Assigned users: {{ number_format($branch->users_count) }}</p></div><span class="badge {{ $branch->is_active ? 'badge-green' : 'badge-red' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span></div>
                <div class="grid gap-3 p-5 md:grid-cols-2">
                    <div><label class="form-label">Code *</label><input name="code" required value="{{ $branch->code }}" @readonly(strtoupper($branch->code) === 'MAIN')></div>
                    <div><label class="form-label">Name *</label><input name="name" required value="{{ $branch->name }}"></div>
                    <div><label class="form-label">Phone</label><input name="phone" value="{{ $branch->phone }}"></div>
                    <div><label class="form-label">Email</label><input type="email" name="email" value="{{ $branch->email }}"></div>
                    <div class="md:col-span-2"><label class="form-label">Address</label><textarea name="address" rows="2">{{ $branch->address }}</textarea></div>
                    <div><label class="form-label">Assigned users *</label><div class="max-h-52 space-y-2 overflow-y-auto rounded-md border border-slate-300 p-3">@foreach($users as $user)<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @checked(in_array($user->id, $assignedUserIds, true))>{{ $user->name }}</label>@endforeach</div></div>
                    <div>
                        <label class="form-label">Default user</label>
                        <select name="default_user_id"><option value="">None</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($branch->users->firstWhere('id', $user->id)?->pivot->is_default)>{{ $user->name }}</option>@endforeach</select>
                        <label class="form-label mt-3">Branch Super Administrator *</label>
                        <select name="branch_administrator_id" required><option value="">Select the responsible administrator</option>@foreach($branchAdministrators as $user)<option value="{{ $user->id }}" @selected($branch->users->firstWhere('id', $user->id)?->pivot->is_branch_administrator)>{{ $user->name }}</option>@endforeach</select>
                        <p class="mt-1 text-xs text-slate-500">Must be included in Assigned users. Only one responsible Branch Super Administrator is permitted.</p>
                        @if(strtoupper($branch->code) === 'MAIN')<p class="mt-3 text-sm text-slate-500">The Main Branch always remains active.</p>@else<label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($branch->is_active)> Active branch</label>@endif
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-slate-200 p-4">
                    @if(strtoupper($branch->code) === 'MAIN')<span class="text-xs font-medium text-slate-500">Main Branch cannot be deleted.</span>@else<button type="submit" form="delete-branch-{{ $branch->id }}" class="btn btn-danger">Delete branch</button>@endif
                    <button class="btn btn-secondary">Save branch</button>
                </div>
            </form>
            @if(strtoupper($branch->code) !== 'MAIN')<form id="delete-branch-{{ $branch->id }}" method="POST" action="{{ route('branches.destroy', $branch) }}" onsubmit="return confirm('Delete {{ addslashes($branch->name) }}? This is only allowed when it has no users, stock, students, or transaction history.');">@csrf @method('DELETE')</form>@endif
        @endforeach
    </section>
</div>
@endsection
