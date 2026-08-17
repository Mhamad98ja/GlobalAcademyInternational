<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasPublicId;

    protected $fillable = ['code', 'name', 'phone', 'email', 'address', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_default', 'is_branch_administrator')->withTimestamps();
    }

    public function bookStocks(): HasMany
    {
        return $this->hasMany(BranchBookStock::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
