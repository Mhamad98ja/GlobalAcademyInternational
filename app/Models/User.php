<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasPublicId;
use App\Services\BranchContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasPublicId;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'whatsapp_number',
        'password',
        'is_active',
        'is_controller_administrator',
        'has_all_branch_access',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_controller_administrator' => 'boolean',
            'has_all_branch_access' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function branches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('is_default', 'is_branch_administrator')->withTimestamps();
    }

    public function custodyStocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserBookStock::class);
    }

    public function passwordResetOtps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PasswordResetOtp::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()->where(function ($query) use ($permission): void {
            $query->where('name', 'super-administrator')
                ->orWhereHas('permissions', fn ($permissions) => $permissions->where('name', $permission));
        })->exists();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function isControllerAdministrator(): bool
    {
        return $this->is_active && $this->is_controller_administrator;
    }

    public function hasGlobalBranchAccess(): bool
    {
        return $this->isControllerAdministrator() || $this->has_all_branch_access;
    }

    public function isBranchAdministrator(?int $branchId = null): bool
    {
        if (! $this->is_active || $this->isControllerAdministrator()) {
            return $this->isControllerAdministrator();
        }

        $branchId ??= app(BranchContext::class)->id();

        return $branchId !== null
            && $this->hasRole('super-administrator')
            && $this->branches()
                ->whereKey($branchId)
                ->wherePivot('is_branch_administrator', true)
                ->exists();
    }

    public function canManageUsersInBranch(?int $branchId = null): bool
    {
        return $this->isControllerAdministrator() || $this->isBranchAdministrator($branchId);
    }

    public function canViewFinancials(): bool
    {
        return $this->isControllerAdministrator()
            || $this->hasPermission('invoices.view')
            || $this->hasPermission('payments.view')
            || $this->hasPermission('purchases.manage');
    }
}
