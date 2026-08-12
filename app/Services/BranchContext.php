<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class BranchContext
{
    public function id(): ?int
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return Branch::query()->where('code', 'MAIN')->value('id') ?? Branch::query()->value('id');
        }

        $selected = session('current_branch_id');
        if ($selected && $this->canUse($user, (int) $selected)) {
            return (int) $selected;
        }

        $default = $user->branches()->where('is_active', true)->orderByDesc('branch_user.is_default')->value('branches.id');
        if ($default) {
            return (int) $default;
        }

        // Existing accounts created before branches were introduced continue in Main Branch.
        return Branch::query()->where('code', 'MAIN')->value('id');
    }

    public function branch(): ?Branch
    {
        $id = $this->id();
        return $id ? Branch::find($id) : null;
    }

    public function canUse(User $user, int $branchId): bool
    {
        return $user->hasRole('super-administrator')
            ? Branch::query()->whereKey($branchId)->where('is_active', true)->exists()
            : $user->branches()->whereKey($branchId)->where('is_active', true)->exists();
    }

    public function accessibleBranches(User $user)
    {
        return $user->hasRole('super-administrator')
            ? Branch::query()->where('is_active', true)->orderBy('name')->get()
            : $user->branches()->where('is_active', true)->orderBy('name')->get();
    }
}
