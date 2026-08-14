<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\AuditService;
use App\Services\BranchContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchesController extends Controller
{
    public function index(): View
    {
        $users = \App\Models\User::query()->with('roles')->where('is_active', true)->orderBy('name')->get();

        return view('branches.index', [
            'branches' => Branch::with('users')->withCount('users')->orderBy('name')->get(),
            'users' => $users,
            'controllerAdministrator' => \App\Models\User::query()->where('is_controller_administrator', true)->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data): void {
            $branch = Branch::create(Arr::only($data, ['code', 'name', 'phone', 'email', 'address', 'notes', 'is_active']));
            $this->syncUsers($branch, $data['user_ids'] ?? [], isset($data['default_user_id']) ? (int) $data['default_user_id'] : null);
        });

        return back()->with('success', 'Branch created and users assigned.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $this->validated($request, $branch);
        DB::transaction(function () use ($branch, $data): void {
            $branch->update(Arr::only($data, ['code', 'name', 'phone', 'email', 'address', 'notes', 'is_active']));
            $this->syncUsers($branch, $data['user_ids'] ?? [], isset($data['default_user_id']) ? (int) $data['default_user_id'] : null);
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
            'default_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
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

    private function syncUsers(Branch $branch, array $userIds, ?int $defaultUserId): void
    {
        $userIds = array_map('intval', $userIds);
        $defaultUserId = $defaultUserId === null ? null : (int) $defaultUserId;
        if ($defaultUserId !== null && ! in_array($defaultUserId, $userIds, true)) {
            throw ValidationException::withMessages(['default_user_id' => 'The default user must be assigned to this branch.']);
        }
        $currentAssignments = $branch->users()->get()->keyBy('id');
        $branch->users()->sync(collect($userIds)->mapWithKeys(fn ($id) => [(int) $id => [
            'is_default' => (int) $id === $defaultUserId,
            'is_branch_administrator' => (bool) ($currentAssignments->get((int) $id)?->pivot->is_branch_administrator ?? false),
        ]])->all());
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
            'deferred_payment_approvals', 'book_distributions', 'book_returns', 'stock_movements',
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
