@extends('layouts.app', ['title' => 'Edit user'])

@section('content')
@php($canTransferControllerAuthority = auth()->user()->isControllerAdministrator() && ! $managedUser->is_controller_administrator && $managedUser->is_active && $managedUser->hasPermission('admin.manage'))
<div class="page-header">
    <div>
        <h1 class="page-title">Edit user</h1>
        <p class="page-subtitle">Update account details, password, or role for {{ $managedUser->name }}.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('admin.users') }}">Cancel</a>
</div>

<form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="panel max-w-2xl" data-submit-lock>
    @csrf
    @method('PUT')
    <div class="space-y-4 p-5">
        <div>
            <label class="form-label">Full name *</label>
            <input name="name" required value="{{ old('name', $managedUser->name) }}">
        </div>
        <div>
            <label class="form-label">Email *</label>
            <input type="email" name="email" required value="{{ old('email', $managedUser->email) }}">
        </div>
        <div>
            <label class="form-label">WhatsApp number</label>
            <input name="whatsapp_number" value="{{ old('whatsapp_number', $managedUser->whatsapp_number) }}" placeholder="+961XXXXXXXX">
            <p class="mt-1 text-xs text-slate-500">Use international format. This is used only for password reset codes.</p>
        </div>
        @if ($canManageBranchAssignments)
            <div>
                <label class="form-label">Role *</label>
                <select name="role_id" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id', $managedUser->roles->first()?->id) == $role->id)>{{ $role->label }}</option>
                    @endforeach
                </select>
            </div>
        <div class="border-t border-slate-200 pt-4">
            <h2 class="panel-title">Branch authority</h2>
            <p class="mt-1 text-xs text-slate-500">Users can work only in assigned branches. The Controller Administrator is the only account with whole-school branch authority.</p>
            <div class="mt-3">
                <label class="form-label">Assigned branches</label>
                <select name="branch_ids[]" multiple size="5">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(in_array($branch->id, old('branch_ids', $managedUser->branches->pluck('id')->all())))>{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="mt-3">
                <label class="form-label">Default branch</label>
                <select name="default_branch_id"><option value="">None</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(old('default_branch_id', $managedUser->branches->firstWhere('pivot.is_default', true)?->id) == $branch->id)>{{ $branch->name }}</option>@endforeach</select>
            </div>
            <div class="mt-3">
                <label class="form-label">Branch Super Administrator for selected branches</label>
                <select name="branch_administrator_ids[]" multiple size="5">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(in_array($branch->id, old('branch_administrator_ids', $managedUser->branches->filter(fn ($branch) => $branch->pivot->is_branch_administrator)->pluck('id')->all())))>{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">A Branch Super Administrator has full operational and financial control only in the selected branches. One responsible Branch Super Administrator is allowed for each branch.</p>
            </div>
        </div>
        @else
            <div class="border-t border-slate-200 pt-4 text-sm text-slate-600">
                <input type="hidden" name="role_id" value="{{ $managedUser->roles->first()?->id }}">
                Role and branch authority are managed only by the Controller Administrator.
            </div>
        @endif
        <div class="border-t border-slate-200 pt-4">
            <h2 class="panel-title">Change password</h2>
            <p class="mt-1 text-xs text-slate-500">Leave both password fields empty to keep the current password.</p>
            <div class="form-grid mt-4">
                <div>
                    <label class="form-label">New password</label>
                    <input type="password" name="password" placeholder="At least 12 characters">
                </div>
                <div>
                    <label class="form-label">Confirm new password</label>
                    <input type="password" name="password_confirmation">
                </div>
            </div>
        </div>
    </div>
    <div class="flex justify-end border-t border-slate-200 p-5">
        @if ($canTransferControllerAuthority)
            <button type="submit" form="transfer-controller-authority" class="btn btn-secondary mr-auto" onclick="return confirm('Transfer Controller Administrator authority to {{ addslashes($managedUser->name) }}?');">Make Controller Administrator</button>
        @endif
        <button class="btn btn-primary">Save user</button>
    </div>
</form>
@if ($canTransferControllerAuthority)
    <form id="transfer-controller-authority" method="POST" action="{{ route('admin.users.controller.transfer', $managedUser) }}">@csrf</form>
@endif
@endsection
