<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchTransferInvoice extends Model
{
    use HasPublicId;

    protected $fillable = [
        'book_transfer_id', 'invoice_number', 'source_branch_id', 'destination_branch_id',
        'issued_on', 'subtotal', 'tax_total', 'total', 'status', 'notes', 'issued_by',
        'accepted_by', 'accepted_at', 'rejected_by', 'rejected_at', 'rejection_reason',
        'settlement_status', 'settled_at', 'settled_by', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'settled_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BookTransfer::class, 'book_transfer_id');
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BranchTransferInvoiceItem::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(BranchCashMovement::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
