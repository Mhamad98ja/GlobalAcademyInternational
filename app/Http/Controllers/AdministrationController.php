<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\BranchContext;
use App\Models\Book;
use App\Models\ResponsiblePerson;
use App\Models\Role;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdministrationController extends Controller
{
    public function users(): View
    {
        return view('admin.users', [
            'users' => User::with('roles', 'branches')->orderBy('name')->paginate(20),
            'roles' => Role::orderBy('label')->get(),
        ]);
    }

    public function storeUser(Request $request, BranchContext $branches): RedirectResponse
    {
        $canManageBranchAssignments = $request->user()->isControllerAdministrator();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'whatsapp_number' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,whatsapp_number'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role_id' => ['required', 'exists:roles,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'default_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'has_all_branch_access' => ['nullable', 'boolean'],
            'branch_administrator_ids' => ['nullable', 'array'],
            'branch_administrator_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
            'has_all_branch_access' => $canManageBranchAssignments && $request->boolean('has_all_branch_access'),
        ]);
        $user->roles()->sync([$data['role_id']]);
        $branchIds = $data['branch_ids'] ?? [];
        $defaultBranchId = isset($data['default_branch_id']) ? (int) $data['default_branch_id'] : null;
        if ($canManageBranchAssignments && $branchIds !== []) {
            $this->syncBranches($user, $branchIds, $defaultBranchId, $data['branch_administrator_ids'] ?? []);
        } elseif ($branchId = $branches->id()) {
            $user->branches()->syncWithoutDetaching([$branchId => ['is_default' => true, 'is_branch_administrator' => false]]);
        }

        return back()->with('success', 'User account created.');
    }

    public function editUser(User $user): View
    {
        return view('admin.user-form', [
            'managedUser' => $user->load('roles', 'branches'),
            'roles' => Role::orderBy('label')->get(),
            'branches' => \App\Models\Branch::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $canManageBranchAssignments = $request->user()->isControllerAdministrator();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'whatsapp_number' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'whatsapp_number')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role_id' => ['required', 'exists:roles,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'default_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'has_all_branch_access' => ['nullable', 'boolean'],
            'branch_administrator_ids' => ['nullable', 'array'],
            'branch_administrator_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
        ];
        if ($canManageBranchAssignments) {
            $attributes['has_all_branch_access'] = $request->boolean('has_all_branch_access');
        }
        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $user->update($attributes);
        $user->roles()->sync([$data['role_id']]);
        if ($canManageBranchAssignments) {
            $this->syncBranches(
                $user,
                $data['branch_ids'] ?? [],
                isset($data['default_branch_id']) ? (int) $data['default_branch_id'] : null,
                $data['branch_administrator_ids'] ?? [],
            );
        }

        return redirect()->route('admin.users')->with('success', 'User account updated.');
    }

    public function toggleUser(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot disable your own account.');
        abort_if($user->is_controller_administrator, 422, 'Transfer Controller Administrator authority before disabling this account.');
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'User status updated.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
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
        return view('admin.roles', ['roles' => Role::with('permissions')->get(), 'permissions' => \App\Models\Permission::orderBy('label')->get()]);
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
        $user->branches()->sync(collect($branchIds)->mapWithKeys(fn ($id) => [(int) $id => [
            'is_default' => (int) $id === $defaultBranchId,
            'is_branch_administrator' => in_array((int) $id, $branchAdministratorIds, true),
        ]])->all());
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
