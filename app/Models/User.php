<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasPublicId;
use App\Services\BranchContext;
use App\Support\PermissionCatalog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicId, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'profile_photo_path',
        'whatsapp_number',
        'teacher_id',
        'student_id',
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withPivot('allowed')->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('is_default', 'is_branch_administrator')->withTimestamps();
    }

    public function custodyStocks(): HasMany
    {
        return $this->hasMany(UserBookStock::class);
    }

    public function passwordResetOtps(): HasMany
    {
        return $this->hasMany(PasswordResetOtp::class);
    }

    public function sentSchoolMessages(): HasMany
    {
        return $this->hasMany(SchoolMessage::class, 'sender_id');
    }

    public function receivedSchoolMessages(): HasMany
    {
        return $this->hasMany(SchoolMessageRecipient::class, 'recipient_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function studentSupportAssignments(): HasMany
    {
        return $this->hasMany(StudentSupportAssignment::class);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isControllerAdministrator()) {
            return true;
        }

        $candidates = PermissionCatalog::candidates($permission);
        $directPermissions = $this->permissions()
            ->whereIn('permissions.name', $candidates)
            ->get()
            ->keyBy('name');
        foreach ($candidates as $candidate) {
            if ($directPermissions->has($candidate)) {
                return (bool) $directPermissions->get($candidate)->pivot->allowed;
            }
        }

        if (str_starts_with($permission, 'student-portal.') && $this->student_id && $this->hasRole('student')) {
            return true;
        }

        return $this->roles()->where(function ($query) use ($candidates): void {
            $query->where('name', 'super-administrator')
                ->orWhereHas('permissions', fn ($permissions) => $permissions->whereIn('name', $candidates));
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
        return $this->isControllerAdministrator();
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
            || $this->hasPermission('finance.view')
            || $this->hasPermission('invoices.view')
            || $this->hasPermission('payments.view')
            || $this->hasPermission('purchases.manage');
    }
}
