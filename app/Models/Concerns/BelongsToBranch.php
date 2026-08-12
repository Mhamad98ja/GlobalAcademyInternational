<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            $branchId = app(BranchContext::class)->id();
            if ($branchId !== null) {
                $query->where($query->getModel()->getTable().'.branch_id', $branchId);
            }
        });

        static::creating(function ($model): void {
            if ($model->branch_id === null) {
                $model->branch_id = app(BranchContext::class)->id();
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }
}
