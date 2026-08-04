<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Services\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'tax_number',
        'payment_terms',
        'opening_balance',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getAccountBalanceAttribute(): string
    {
        $purchaseTotal = $this->getAttribute('purchases_total_sum');
        $amountPaid = $this->getAttribute('purchases_paid_sum');

        if ($purchaseTotal === null) {
            $purchaseTotal = $this->purchases()->sum('total');
        }

        if ($amountPaid === null) {
            $amountPaid = $this->purchases()->sum('amount_paid');
        }

        $balance = Money::cents((string) $this->opening_balance)
            + Money::cents((string) $purchaseTotal)
            - Money::cents((string) $amountPaid);

        return Money::decimal(max(0, $balance));
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(SupplierPurchase::class);
    }
}
