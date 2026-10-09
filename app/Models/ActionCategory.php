<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ActionCategory extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'name', 'kind', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function types()
    {
        return $this->hasMany(ActionType::class);
    }
}
