<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    protected $fillable = [
        'supplier_return_id',
        'supplier_purchase_item_id',
        'book_id',
        'quantity',
        'credit_total',
        'reason',
    ];

    protected function casts(): array
    {
        return ['credit_total' => 'decimal:2'];
    }

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseItem::class, 'supplier_purchase_item_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
