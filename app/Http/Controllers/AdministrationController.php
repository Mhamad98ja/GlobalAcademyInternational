<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\ResponsiblePerson;
use App\Models\Role;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\Teacher;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdministrationController extends Controller
{
    public function users(Request $request, BranchContext $branches): View
    {
        $actor = auth()->user();
        $currentBranchId = $branches->id();
        $canManageBranchAssignments = $actor->isControllerAdministrator();
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'disabled', 'all'])],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $status = $filters['status'] ?? 'active';
        $query = User::with('roles', 'branches')
            ->when(! $canManageBranchAssignments, fn ($users) => $users->whereHas('branches', fn ($assignedBranches) => $assignedBranches->whereKey($currentBranchId)))
            ->when($status !== 'all', fn ($users) => $users->where('is_active', $status === 'active'))
            ->when($filters['role_id'] ?? null, fn ($users, $roleId) => $users->whereHas('roles', fn ($roles) => $roles->whereKey($roleId)))
            ->when($filters['q'] ?? null, fn ($users, $search) => $users->where(function ($names) use ($search): void {
                $names->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            }));

        return view('admin.users', [
            'users' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => $filters + ['status' => $status],
            'roles' => Role::orderBy('label')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'canManageBranchAssignments' => $canManageBranchAssignments,
            'currentBranchId' => $currentBranchId,
            'teachers' => Teacher::where('is_active', true)->orderBy('first_name')->get(),
            'students' => Student::query()->with('registrations.grade', 'registrations.section')->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    public function storeUser(Request $request, BranchContext $branches): RedirectResponse
    {
        $actor = $request->user();
        $currentBranchId = $branches->id();
        $canManageBranchAssignments = $actor->isControllerAdministrator();
        abort_unless($actor->canManageUsersInBranch($currentBranchId), 403);
        $role = $canManageBranchAssignments ? Role::find($request->integer('role_id')) : Role::where('name', 'employee')->first();
        $roleName = $role?->name;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'whatsapp_number' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,whatsapp_number'],
            'teacher_id' => [Rule::requiredIf($roleName === 'teacher'), 'nullable', 'integer', 'exists:teachers,id', Rule::unique('users', 'teacher_id')],
            'student_id' => [Rule::requiredIf($roleName === 'student'), 'nullable', 'integer', 'exists:students,id', Rule::unique('users', 'student_id')],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role_id' => [$canManageBranchAssignments ? 'required' : 'nullable', 'exists:roles,id'],
            'branch_ids' => [Rule::requiredIf($canManageBranchAssignments), 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'default_branch_id' => [Rule::requiredIf($canManageBranchAssignments), 'nullable', 'integer', 'exists:branches,id'],
            'has_all_branch_access' => ['nullable', 'boolean'],
            'branch_administrator_ids' => ['nullable', 'array'],
            'branch_administrator_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'student_id' => $data['student_id'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
            'has_all_branch_access' => false,
        ]);
        $roleId = $canManageBranchAssignments
            ? (int) $data['role_id']
            : (int) Role::where('name', 'employee')->value('id');
        $user->roles()->sync([$roleId]);
        $branchIds = $data['branch_ids'] ?? [];
        $defaultBranchId = isset($data['default_branch_id']) ? (int) $data['default_branch_id'] : null;
        if ($canManageBranchAssignments && ($branchIds !== [] || $user->hasRole('super-administrator'))) {
            $this->syncBranches($user, $branchIds, $defaultBranchId, $data['branch_administrator_ids'] ?? []);
        } elseif ($currentBranchId) {
            $user->branches()->syncWithoutDetaching([$currentBranchId => ['is_default' => true, 'is_branch_administrator' => false]]);
        }

        return back()->with('success', 'User account created.');
    }

    public function editUser(User $user, BranchContext $branches): View
    {
        $actor = auth()->user();
        $this->ensureCanManageUser($actor, $user, $branches->id());

        return view('admin.user-form', [
            'managedUser' => $user->load('roles', 'branches'),
            'roles' => Role::orderBy('label')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'canManageBranchAssignments' => $actor->isControllerAdministrator(),
            'teachers' => Teacher::where('is_active', true)->orderBy('first_name')->get(),
            'students' => Student::query()->with('registrations.grade', 'registrations.section')->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    public function updateUser(Request $request, User $user, BranchContext $branches): RedirectResponse
    {
        $actor = $request->user();
        $currentBranchId = $branches->id();
        $this->ensureCanManageUser($actor, $user, $currentBranchId);
        $canManageBranchAssignments = $actor->isControllerAdministrator();
        $role = $canManageBranchAssignments ? Role::find($request->integer('role_id')) : $user->roles()->first();
        $roleName = $role?->name;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'whatsapp_number' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'whatsapp_number')->ignore($user->id)],
            'teacher_id' => [Rule::requiredIf($roleName === 'teacher'), 'nullable', 'integer', 'exists:teachers,id', Rule::unique('users', 'teacher_id')->ignore($user)],
            'student_id' => [Rule::requiredIf($roleName === 'student'), 'nullable', 'integer', 'exists:students,id', Rule::unique('users', 'student_id')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role_id' => [$canManageBranchAssignments ? 'required' : 'nullable', 'exists:roles,id'],
            'branch_ids' => [Rule::requiredIf($canManageBranchAssignments), 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'default_branch_id' => [Rule::requiredIf($canManageBranchAssignments), 'nullable', 'integer', 'exists:branches,id'],
            'has_all_branch_access' => ['nullable', 'boolean'],
            'branch_administrator_ids' => ['nullable', 'array'],
            'branch_administrator_ids.*' => ['integer', 'exists:branches,id'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=3000,max_height=3000'],
            'remove_profile_photo' => ['nullable', 'boolean'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'student_id' => $data['student_id'] ?? null,
        ];
        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $user->update($attributes);
        if ($request->hasFile('profile_photo')) {
            $oldPhoto = $user->profile_photo_path;
            $newPhoto = $request->file('profile_photo')->store('user-profile-photos/'.$user->public_id, 'public');
            $user->update(['profile_photo_path' => $newPhoto]);
            if ($oldPhoto && $oldPhoto !== $newPhoto) {
                Storage::disk('public')->delete($oldPhoto);
            }
        } elseif ($request->boolean('remove_profile_photo') && $user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);
        }
        if ($canManageBranchAssignments) {
            if ($user->is_controller_administrator && Role::whereKey($data['role_id'])->where('name', '!=', 'super-administrator')->exists()) {
                abort(422, 'The Controller Administrator must remain a Branch Super Administrator.');
            }
            $user->roles()->sync([(int) $data['role_id']]);
            $this->syncBranches(
                $user,
                $data['branch_ids'] ?? [],
                isset($data['default_branch_id']) ? (int) $data['default_branch_id'] : null,
                $data['branch_administrator_ids'] ?? [],
            );
        }

        return redirect()->route('admin.users')->with('success', 'User account updated.');
    }

    public function userPhoto(User $user, BranchContext $branches)
    {
        $this->ensureCanManageUser(auth()->user(), $user, $branches->id());
        abort_unless($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path), 404);

        return Storage::disk('public')->response($user->profile_photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function toggleUser(User $user, BranchContext $branches): RedirectResponse
    {
        $this->ensureCanManageUser(auth()->user(), $user, $branches->id());
        abort_if($user->is(auth()->user()), 422, 'You cannot disable your own account.');
        abort_if($user->is_controller_administrator, 422, 'Transfer Controller Administrator authority before disabling this account.');
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'User status updated.');
    }

    public function destroyUser(User $user, BranchContext $branches): RedirectResponse
    {
        $this->ensureCanManageUser(auth()->user(), $user, $branches->id());
        abort_if($user->is(auth()->user()), 422, 'You cannot delete your own account.');
        abort_if($user->is_controller_administrator, 422, 'Transfer Controller Administrator authority before deleting this account.');

        if ($user->hasRole('super-administrator')) {
            $hasAnotherActiveAdministrator = User::query()
                ->where('is_active', true)
                ->whereKeyNot($user->getKey())
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'super-administrator'))
                ->exists();

            abort_unless($hasAnotherActiveAdministrator, 422, 'At least one active super administrator must remain.');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted. You can restore the account from the recycle bin.');
    }

    public function transferControllerAuthority(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'This account is already the Controller Administrator.');
        abort_unless($user->is_active && $user->hasPermission('admin.manage'), 422, 'The new Controller Administrator must be an active administrator.');

        DB::transaction(function () use ($user): void {
            User::query()->where('is_controller_administrator', true)->update(['is_controller_administrator' => false]);
            $user->forceFill([
                'is_controller_administrator' => true,
                'has_all_branch_access' => true,
            ])->save();
        });

        return redirect()->route('admin.users')->with('success', "{$user->name} is now the Controller Administrator.");
    }

    public function roles(): View
    {
        return view('admin.roles', ['roles' => Role::with('permissions')->get(), 'permissions' => Permission::orderBy('label')->get()]);
    }

    public function audit(): View
    {
        return view('admin.audit', ['logs' => AuditLog::with('user')->latest()->paginate(30)]);
    }

    public function recycleBin(): View
    {
        return view('admin.recycle-bin', [
            'students' => Student::onlyTrashed()->latest()->get(),
            'books' => Book::onlyTrashed()->latest()->get(),
            'suppliers' => Supplier::onlyTrashed()->latest()->get(),
            'people' => ResponsiblePerson::onlyTrashed()->latest()->get(),
            'users' => User::onlyTrashed()->latest()->get(),
        ]);
    }

    public function restore(Request $request, string $type, string $id): RedirectResponse
    {
        $models = ['student' => Student::class, 'book' => Book::class, 'supplier' => Supplier::class, 'responsible-person' => ResponsiblePerson::class, 'user' => User::class];
        abort_unless(isset($models[$type]), 404);
        $model = $models[$type]::onlyTrashed()->where('public_id', $id)->firstOrFail();
        $model->restore();

        return back()->with('success', 'Record restored.');
    }

    public function settings(): View
    {
        return view('admin.settings', ['settings' => SystemSetting::pluck('value', 'key')]);
    }

    private function syncBranches(User $user, array $branchIds, ?int $defaultBranchId, array $branchAdministratorIds = []): void
    {
        $branchIds = array_map('intval', $branchIds);
        $branchAdministratorIds = array_map('intval', $branchAdministratorIds);
        $defaultBranchId = $defaultBranchId === null ? null : (int) $defaultBranchId;
        if ($defaultBranchId !== null && ! in_array($defaultBranchId, $branchIds, true)) {
            abort(422, 'The default branch must be assigned to this user.');
        }
        if (array_diff($branchAdministratorIds, $branchIds) !== []) {
            abort(422, 'A branch administrator must be assigned to that branch.');
        }
        if ($branchAdministratorIds !== [] && ! $user->hasRole('super-administrator')) {
            abort(422, 'Only a Branch Super Administrator can be responsible for a branch.');
        }
        if ($user->hasRole('super-administrator') && ! $user->isControllerAdministrator() && $branchAdministratorIds === []) {
            abort(422, 'A Branch Super Administrator must be responsible for at least one assigned branch.');
        }

        foreach ($branchAdministratorIds as $branchId) {
            DB::table('branch_user')
                ->where('branch_id', $branchId)
                ->where('user_id', '!=', $user->id)
                ->where('is_branch_administrator', true)
                ->update(['is_branch_administrator' => false, 'updated_at' => now()]);
        }
        $user->branches()->sync(collect($branchIds)->mapWithKeys(fn ($id) => [(int) $id => [
            'is_default' => (int) $id === $defaultBranchId,
            'is_branch_administrator' => in_array((int) $id, $branchAdministratorIds, true),
        ]])->all());
    }

    private function ensureCanManageUser(User $actor, User $managedUser, ?int $branchId): void
    {
        abort_unless($actor->canManageUsersInBranch($branchId), 403);

        if ($actor->isControllerAdministrator()) {
            return;
        }

        abort_if($managedUser->is_controller_administrator, 403);
        abort_if($managedUser->hasRole('super-administrator') && ! $managedUser->is($actor), 403);
        abort_unless($branchId !== null && $managedUser->branches()->whereKey($branchId)->exists(), 403);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'school_address' => ['nullable', 'string', 'max:1000'],
            'school_phone' => ['nullable', 'string', 'max:50'],
            'allow_negative_stock' => ['nullable', 'boolean'],
        ]);
        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        return back()->with('success', 'Settings saved.');
    }
}
