<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookTransfer extends Model
{
    use HasPublicId;

    protected $fillable = [
        'transfer_number', 'transfer_type', 'source_branch_id', 'destination_branch_id',
        'source_user_id', 'destination_user_id', 'academic_year_id', 'transferred_on',
        'status', 'notes', 'receipt_notes', 'rejection_reason', 'reversal_notes', 'created_by', 'completed_by',
        'accepted_by', 'accepted_at', 'rejected_by', 'rejected_at', 'reversed_by', 'reversed_at',
    ];

    protected function casts(): array
    {
        return ['transferred_on' => 'date', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function destinationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destination_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookTransferItem::class);
    }

    public function financialInvoice(): HasOne
    {
        return $this->hasOne(BranchTransferInvoice::class);
    }
}
