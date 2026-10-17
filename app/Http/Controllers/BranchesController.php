<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BranchContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class BranchesController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'edit' => ['nullable', 'integer', 'exists:branches,id'],
            'create' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $editingBranch = isset($filters['edit']) ? Branch::query()->with('users.roles')->findOrFail($filters['edit']) : null;
        $selectedUserIds = collect(old('user_ids', $editingBranch?->users->pluck('id')->all() ?? []))
            ->map(fn ($id) => (int) $id)->filter()->unique()->all();

        return view('branches.index', [
            'branches' => Branch::query()->withCount('users', 'students')
                ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')))
                ->orderBy('name')->paginate(12)->withQueryString(),
            'editingBranch' => $editingBranch,
            'selectedUsers' => User::query()->with('roles')->whereIn('id', $selectedUserIds)->orderBy('name')->get(),
            'controllerAdministrator' => User::query()->where('is_controller_administrator', true)->first(),
        ]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'kind' => ['nullable', Rule::in(['all', 'administrators', 'teachers', 'employees', 'students'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $search = trim($data['q']);
        $kind = $data['kind'] ?? 'all';
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        return response()->json(User::query()->with('roles')->where('is_active', true)
            ->where(fn ($users) => $users->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
            ->where(fn ($users) => $users->whereNull('student_id')
                ->when($branchId !== null, fn ($users) => $users->orWhereHas('student', fn ($students) => $students
                    ->withoutGlobalScopes()->where('branch_id', $branchId))))
            ->when($kind === 'administrators', fn ($users) => $users->where(fn ($query) => $query
                ->where('is_controller_administrator', true)->orWhereHas('roles', fn ($roles) => $roles->where('name', 'super-administrator'))))
            ->when($kind === 'teachers', fn ($users) => $users->where(fn ($query) => $query
                ->whereNotNull('teacher_id')->orWhereHas('roles', fn ($roles) => $roles->where('name', 'teacher'))))
            ->when($kind === 'students', fn ($users) => $users->where(fn ($query) => $query
                ->whereNotNull('student_id')->orWhereHas('roles', fn ($roles) => $roles->where('name', 'student'))))
            ->when($kind === 'employees', fn ($users) => $users->whereNull('teacher_id')->whereNull('student_id')
                ->where('is_controller_administrator', false)
                ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', ['super-administrator', 'teacher', 'student'])))
            ->orderBy('name')->limit(12)->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'can_administer_branch' => ! $user->is_controller_administrator && $user->hasRole('super-administrator'),
                'kind' => $this->userKind($user),
            ]));
    }

    private function userKind(User $user): string
    {
        if ($user->student_id || $user->hasRole('student')) {
            return 'students';
        }
        if ($user->teacher_id || $user->hasRole('teacher')) {
            return 'teachers';
        }

        return $user->is_controller_administrator || $user->hasRole('super-administrator') ? 'administrators' : 'employees';
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $branch = DB::transaction(function () use ($data): Branch {
            $branch = Branch::create(Arr::only($data, ['code', 'name', 'phone', 'email', 'address', 'notes', 'is_active']));
            $administratorId = isset($data['branch_administrator_id']) ? (int) $data['branch_administrator_id'] : null;
            $userIds = $data['user_ids'] ?? [];
            if ($administratorId === null) {
                $administrator = User::create([
                    'name' => $data['administrator_name'],
                    'email' => $data['administrator_email'],
                    'password' => $data['administrator_password'],
                    'is_active' => true,
                    'has_all_branch_access' => false,
                ]);
                $administrator->roles()->sync([Role::where('name', 'super-administrator')->value('id')]);
                $administratorId = $administrator->id;
                $userIds[] = $administratorId;
            }
            $this->syncUsers($branch, $userIds, $administratorId);

            return $branch;
        });

        return redirect()->route('branches.index', ['edit' => $branch->id])->with('success', 'Branch created and users assigned.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $this->validated($request, $branch);
        DB::transaction(function () use ($branch, $data): void {
            $branch->update(Arr::only($data, ['code', 'name', 'phone', 'email', 'address', 'notes', 'is_active']));
            $this->syncUsers($branch, $data['user_ids'] ?? [], (int) $data['branch_administrator_id']);
        });

        return back()->with('success', 'Branch updated.');
    }

    public function destroy(Branch $branch, AuditService $audit): RedirectResponse
    {
        $this->assertMayBeDeleted($branch);

        $oldValues = $branch->only(['code', 'name', 'phone', 'email', 'address', 'notes', 'is_active']);
        DB::transaction(function () use ($branch, $audit, $oldValues): void {
            $branch->delete();
            $audit->log('branch.deleted', $branch, $oldValues);
        });

        return back()->with('success', 'Branch deleted.');
    }

    public function select(Request $request, BranchContext $branches): RedirectResponse
    {
        abort_unless($request->user()->isControllerAdministrator(), 403);
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']]);
        abort_unless($branches->canUse($request->user(), (int) $data['branch_id']), 403);
        session(['current_branch_id' => (int) $data['branch_id']]);

        return back()->with('success', 'Active branch changed.');
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'branch_administrator_id' => [$branch ? 'required' : 'required_without:administrator_name', 'nullable', 'integer', 'exists:users,id'],
            'administrator_name' => [$branch ? 'prohibited' : 'required_without:branch_administrator_id', 'nullable', 'string', 'max:255'],
            'administrator_email' => [$branch ? 'prohibited' : 'required_with:administrator_name', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'administrator_password' => [$branch ? 'prohibited' : 'required_with:administrator_name', 'nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($branch === null && empty($data['branch_administrator_id']) && (empty($data['administrator_name']) || empty($data['administrator_email']) || empty($data['administrator_password']))) {
            throw ValidationException::withMessages(['branch_administrator_id' => 'Choose an existing Branch Administrator or create a new one.']);
        }
        $data['is_active'] = $request->boolean('is_active');

        // The Main Branch is the permanent home for the central roster and
        // the fallback context for users. It must keep its stable code and
        // remain available.
        if ($branch !== null && strtoupper($branch->code) === 'MAIN') {
            $data['code'] = 'MAIN';
            $data['is_active'] = true;
        }

        return $data;
    }

    private function syncUsers(Branch $branch, array $userIds, int $branchAdministratorId): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $branchAdministrator = User::query()->with('roles')->findOrFail($branchAdministratorId);
        if (! in_array($branchAdministratorId, $userIds, true)) {
            throw ValidationException::withMessages(['branch_administrator_id' => 'The Branch Administrator must be assigned to this branch.']);
        }
        if (! $branchAdministrator->is_active || $branchAdministrator->is_controller_administrator || ! $branchAdministrator->hasRole('super-administrator')) {
            throw ValidationException::withMessages(['branch_administrator_id' => 'Choose an active Branch Administrator for this branch.']);
        }
        foreach (User::query()->whereIn('id', $userIds)->whereNotNull('student_id')->get(['id', 'student_id', 'name']) as $studentUser) {
            $studentBranchId = Student::withoutGlobalScopes()->whereKey($studentUser->student_id)->value('branch_id');
            if ((int) $studentBranchId !== $branch->id) {
                throw ValidationException::withMessages(['user_ids' => "{$studentUser->name} belongs to another branch. Transfer the student profile first."]);
            }
        }

        $existingDefaults = DB::table('branch_user')->where('branch_id', $branch->id)->pluck('is_default', 'user_id');
        $defaultUserIds = DB::table('branch_user')->whereIn('user_id', $userIds)->where('is_default', true)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $removedUserIds = $existingDefaults->keys()->map(fn ($id) => (int) $id)->diff($userIds);
        $replacementDefaults = [];
        foreach ($removedUserIds as $userId) {
            $user = User::query()->find($userId);
            if ($user?->student_id) {
                throw ValidationException::withMessages(['user_ids' => "{$user->name} is a student account. Manage the student's branch from their profile."]);
            }
            $replacementBranchId = DB::table('branch_user')->join('branches', 'branches.id', '=', 'branch_user.branch_id')
                ->where('branch_user.user_id', $userId)->where('branch_user.branch_id', '!=', $branch->id)
                ->where('branches.is_active', true)->value('branches.id');
            if ($user?->is_active && ! $user->isControllerAdministrator() && ! $replacementBranchId) {
                throw ValidationException::withMessages(['user_ids' => "{$user->name} needs another active branch before being removed from this one."]);
            }
            if ($existingDefaults[$userId] && $replacementBranchId) {
                $replacementDefaults[$userId] = (int) $replacementBranchId;
            }
        }

        $branch->users()->sync(collect($userIds)->mapWithKeys(fn ($id) => [(int) $id => [
            'is_default' => $existingDefaults->has($id) ? (bool) $existingDefaults[$id] : ! in_array($id, $defaultUserIds, true),
            'is_branch_administrator' => (int) $id === $branchAdministratorId,
        ]])->all());

        foreach ($replacementDefaults as $userId => $replacementBranchId) {
            DB::table('branch_user')->where('user_id', $userId)->update(['is_default' => false]);
            DB::table('branch_user')->where('user_id', $userId)->where('branch_id', $replacementBranchId)->update(['is_default' => true]);
        }

        if (! $branch->is_active) {
            foreach ($userIds as $userId) {
                $user = User::query()->find($userId);
                $replacementBranchId = DB::table('branch_user')->join('branches', 'branches.id', '=', 'branch_user.branch_id')
                    ->where('branch_user.user_id', $userId)->where('branch_user.branch_id', '!=', $branch->id)
                    ->where('branches.is_active', true)->value('branches.id');
                if ($user?->is_active && ! $user->isControllerAdministrator() && ! $replacementBranchId) {
                    throw ValidationException::withMessages(['is_active' => "Assign {$user->name} to another active branch before deactivating this one."]);
                }
                if ($replacementBranchId && DB::table('branch_user')->where('branch_id', $branch->id)->where('user_id', $userId)->where('is_default', true)->exists()) {
                    DB::table('branch_user')->where('user_id', $userId)->update(['is_default' => false]);
                    DB::table('branch_user')->where('user_id', $userId)->where('branch_id', $replacementBranchId)->update(['is_default' => true]);
                }
            }
        }
    }

    private function assertMayBeDeleted(Branch $branch): void
    {
        if (strtoupper($branch->code) === 'MAIN') {
            throw ValidationException::withMessages(['branch' => 'The Main Branch cannot be deleted.']);
        }

        $blockers = [];
        if ($branch->users()->exists()) {
            $blockers[] = 'assigned users';
        }

        foreach ([
            'students', 'student_registrations', 'class_book_lists', 'supplier_purchases',
            'supplier_returns', 'supplier_payments', 'invoices', 'payments', 'payment_reversals',
            'book_distributions', 'book_returns', 'stock_movements',
            'stock_adjustments', 'credit_notes', 'refunds', 'audit_logs', 'branch_book_stocks',
            'user_book_stocks',
        ] as $table) {
            if (DB::table($table)->where('branch_id', $branch->id)->exists()) {
                $blockers[] = str_replace('_', ' ', $table);
            }
        }

        if (DB::table('book_transfers')
            ->where('source_branch_id', $branch->id)
            ->orWhere('destination_branch_id', $branch->id)
            ->exists()) {
            $blockers[] = 'book transfer history';
        }

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'branch' => 'This branch cannot be deleted because it has '.implode(', ', $blockers).'. Reassign the records or deactivate the branch instead.',
            ]);
        }
    }
}
