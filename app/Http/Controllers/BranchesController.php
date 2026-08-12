<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchesController extends Controller
{
    public function index(): View
    {
        return view('branches.index', [
            'branches' => Branch::withCount('users')->orderBy('name')->get(),
            'users' => \App\Models\User::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $branch = Branch::create($data);
        $this->syncUsers($branch, $data['user_ids'] ?? [], $data['default_user_id'] ?? null);

        return back()->with('success', 'Branch created and users assigned.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $this->validated($request, $branch);
        $branch->update($data);
        $this->syncUsers($branch, $data['user_ids'] ?? [], $data['default_user_id'] ?? null);

        return back()->with('success', 'Branch updated.');
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
        return $request->validate([
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
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function syncUsers(Branch $branch, array $userIds, ?int $defaultUserId): void
    {
        if ($defaultUserId !== null && ! in_array($defaultUserId, $userIds, true)) {
            abort(422, 'The default user must be assigned to this branch.');
        }
        $branch->users()->sync(collect($userIds)->mapWithKeys(fn ($id) => [(int) $id => ['is_default' => (int) $id === $defaultUserId]])->all());
    }
}
