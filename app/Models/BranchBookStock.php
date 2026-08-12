<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchBookStock extends Model
{
    protected $fillable = ['branch_id', 'book_id', 'quantity_on_hand', 'reserved_quantity'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return (int) $this->quantity_on_hand - (int) $this->reserved_quantity;
    }
}
