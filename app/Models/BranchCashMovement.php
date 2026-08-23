<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchCashMovement extends Model
{
    use HasPublicId;

    protected $fillable = [
        'movement_number', 'branch_cash_account_id', 'branch_id', 'counterparty_branch_id', 'book_transfer_id',
        'branch_transfer_invoice_id', 'type', 'amount', 'balance_before', 'balance_after', 'settled_on',
        'reference_number', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'settled_on' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BranchCashAccount::class, 'branch_cash_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function counterpartyBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'counterparty_branch_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BookTransfer::class, 'book_transfer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BranchTransferInvoice::class, 'branch_transfer_invoice_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
