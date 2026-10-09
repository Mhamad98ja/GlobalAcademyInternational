<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class ActionType extends Model
{
    use BelongsToBranch, HasPublicId;

    protected $fillable = ['branch_id', 'action_category_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(ActionCategory::class, 'action_category_id');
    }
}
