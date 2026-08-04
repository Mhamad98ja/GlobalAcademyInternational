<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Services\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPurchase extends Model
{
    use HasPublicId;

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'academic_year_id',
        'purchase_date',
        'supplier_invoice_number',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'amount_paid',
        'payment_status',
        'status',
        'attachment_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function getBalanceDueAttribute(): string
    {
        return Money::decimal(max(0, Money::cents((string) $this->total) - Money::cents((string) $this->amount_paid) - Money::cents($this->return_credit_total)));
    }

    public function getReturnCreditTotalAttribute(): string
    {
        $credit = $this->getAttribute('supplier_returns_credit_total_sum');

        if ($this->relationLoaded('supplierReturns')) {
            return Money::decimal($this->supplierReturns->reduce(
                fn (int $total, SupplierReturn $return) => $total + Money::cents((string) $return->credit_total),
                0,
            ));
        }

        if ($credit === null) {
            $credit = $this->supplierReturns()->sum('credit_total');
        }

        return Money::decimal(Money::cents((string) $credit));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierPurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function supplierReturns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }
}
