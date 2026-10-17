@extends('layouts.app', ['title' => 'Edit user'])

@section('content')
@php($canTransferControllerAuthority = auth()->user()->isControllerAdministrator() && ! $managedUser->is_controller_administrator && $managedUser->is_active && $managedUser->hasPermission('admin.manage'))
@php($assignedBranchIds = collect(old('branch_ids', $managedUser->branches->pluck('id')->all()))->map(fn ($id) => (int) $id)->all())
@php($branchAdministratorIds = collect(old('branch_administrator_ids', $managedUser->branches->filter(fn ($branch) => $branch->pivot->is_branch_administrator)->pluck('id')->all()))->map(fn ($id) => (int) $id)->all())
<div class="page-header">
    <div>
        <h1 class="page-title">Edit user</h1>
        <p class="page-subtitle">Update account details, password, or role for {{ $managedUser->name }}.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('admin.users') }}">Cancel</a>
</div>

<form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="panel max-w-2xl" data-submit-lock enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="space-y-4 p-5">
        <div class="flex flex-wrap items-center gap-5 border-b border-slate-200 pb-5">
            <div class="h-20 w-20 overflow-hidden rounded-full bg-slate-100 ring-4 ring-slate-50">
                @if($managedUser->profile_photo_path)
                    <img src="{{ route('admin.users.photo', $managedUser) }}" alt="Profile photo of {{ $managedUser->name }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-2xl font-semibold text-slate-500">{{ str($managedUser->name)->trim()->substr(0, 1)->upper() }}</div>
                @endif
            </div>
            <div class="min-w-64 flex-1">
                <h2 class="panel-title">Profile photo</h2>
                <p class="mt-1 text-xs text-slate-500">Add or replace this person’s photo from their profile. JPG, PNG, or WebP up to 4 MB.</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="max-w-full text-sm">
                    @if($managedUser->profile_photo_path)<label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remove_profile_photo" value="1"> Remove current photo</label>@endif
                </div>
            </div>
        </div>
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
        <div data-role-field="teacher">
            <label class="form-label">Linked teacher profile</label>
            <select name="teacher_id"><option value="">Not a teacher account</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id', $managedUser->teacher_id) == $teacher->id)>{{ $teacher->employee_number ? $teacher->employee_number.' · ' : '' }}{{ $teacher->full_name }}</option>@endforeach</select>
            <p class="mt-1 text-xs text-slate-500">Teachers need this link to enter marks only for the subjects they teach.</p>
        </div>
        <div data-role-field="student">
            <label class="form-label">Linked student profile</label>
            <x-student-picker name="student_id" :selected-id="old('student_id', $managedUser->student_id)" :selected-label="$managedUser->student?->full_name" placeholder="Type student name or number" />
            <p class="mt-1 text-xs text-slate-500">Student portal accounts are created from the parent / guardian profile. This link is shown only so an existing student account can be maintained; it cannot be moved to another student here.</p>
        </div>
        @if ($canManageBranchAssignments)
            <div>
                <label class="form-label">Role *</label>
                <select name="role_id" required data-user-role-select>
                    @foreach ($roles as $role)
                        @if ($role->name !== 'student' || $managedUser->student_id)
                            <option value="{{ $role->id }}" data-role-name="{{ $role->name }}" @selected(old('role_id', $managedUser->roles->first()?->id) == $role->id)>{{ $role->name === 'super-administrator' ? 'Branch Administrator' : $role->label }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        <div class="border-t border-slate-200 pt-4">
            <h2 class="panel-title">Branch authority</h2>
            <p class="mt-1 text-xs text-slate-500">Assigned branches are where this person can work. The Global Super Administrator alone controls the whole school.</p>
            <div class="mt-3">
                <label class="form-label">Assigned branches</label>
                <div class="max-h-48 space-y-2 overflow-y-auto rounded-md border border-slate-300 p-3">
                    @foreach ($branches as $branch)
                        <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $assignedBranchIds, true))>{{ $branch->name }} ({{ $branch->code }})</label>
                    @endforeach
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label">Starting branch</label>
                <select name="default_branch_id"><option value="">None</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(old('default_branch_id', $managedUser->branches->firstWhere('pivot.is_default', true)?->id) == $branch->id)>{{ $branch->name }}</option>@endforeach</select>
                <p class="mt-1 text-xs text-slate-500">Used only when this person is assigned to more than one branch. It does not grant extra access.</p>
            </div>
            <div class="mt-3" data-branch-administrator-selection>
                <label class="form-label">Responsible Branch Administrator for selected branches</label>
                <div class="max-h-48 space-y-2 overflow-y-auto rounded-md border border-slate-300 p-3">
                    @foreach ($branches as $branch)
                        <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="branch_administrator_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $branchAdministratorIds, true))>{{ $branch->name }} ({{ $branch->code }})</label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-slate-500">A Branch Administrator can manage only assigned branches and cannot change global settings. Assign a replacement from the Branches page before removing an existing responsibility.</p>
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
