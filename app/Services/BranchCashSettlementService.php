<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BranchCashAccount;
use App\Models\BranchCashMovement;
use App\Models\BranchTransferInvoice;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Posts the two equal and opposite cash-control entries for an accepted
 * branch transfer. A negative balance is a visible internal amount owed by a
 * branch; it is not customer revenue or supplier debt.
 */
final class BranchCashSettlementService
{
    public function __construct(private readonly AuditService $audit) {}

    public function settle(BranchTransferInvoice $invoice, User $user): void
    {
        $invoice = BranchTransferInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
        if ($invoice->settlement_status === 'settled') {
            return;
        }
        if ($invoice->status !== 'accepted') {
            throw ValidationException::withMessages(['transfer' => 'Only an accepted branch transfer can be settled.']);
        }

        $amount = Money::cents((string) $invoice->total);
        if ($amount < 1) {
            $invoice->update(['settlement_status' => 'not_required', 'settled_at' => now(), 'settled_by' => $user->id]);

            return;
        }

        [$source, $destination] = $this->lockedAccounts($invoice->source_branch_id, $invoice->destination_branch_id);
        $sourceBefore = Money::cents((string) $source->balance);
        $destinationBefore = Money::cents((string) $destination->balance);
        $sourceAfter = $sourceBefore + $amount;
        $destinationAfter = $destinationBefore - $amount;
        $source->update(['balance' => Money::decimal($sourceAfter)]);
        $destination->update(['balance' => Money::decimal($destinationAfter)]);

        $this->movement($source, $destination, $invoice, 'interbranch_transfer_receipt', $amount, $sourceBefore, $sourceAfter, $user);
        $this->movement($destination, $source, $invoice, 'interbranch_transfer_payment', -$amount, $destinationBefore, $destinationAfter, $user);
        $invoice->update(['settlement_status' => 'settled', 'settled_at' => now(), 'settled_by' => $user->id]);
        $this->audit->log('branch_transfer_cash.settled', $invoice, [], [
            'invoice_number' => $invoice->invoice_number,
            'amount' => Money::decimal($amount),
            'source_branch_id' => $invoice->source_branch_id,
            'destination_branch_id' => $invoice->destination_branch_id,
        ]);
    }

    public function reverse(BranchTransferInvoice $invoice, User $user, ?string $notes = null): void
    {
        $invoice = BranchTransferInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
        if ($invoice->settlement_status !== 'settled') {
            return;
        }

        $amount = Money::cents((string) $invoice->total);
        [$source, $destination] = $this->lockedAccounts($invoice->source_branch_id, $invoice->destination_branch_id);
        $sourceBefore = Money::cents((string) $source->balance);
        $destinationBefore = Money::cents((string) $destination->balance);
        $sourceAfter = $sourceBefore - $amount;
        $destinationAfter = $destinationBefore + $amount;
        $source->update(['balance' => Money::decimal($sourceAfter)]);
        $destination->update(['balance' => Money::decimal($destinationAfter)]);

        $this->movement($source, $destination, $invoice, 'interbranch_transfer_settlement_reversal_out', -$amount, $sourceBefore, $sourceAfter, $user, $notes);
        $this->movement($destination, $source, $invoice, 'interbranch_transfer_settlement_reversal_in', $amount, $destinationBefore, $destinationAfter, $user, $notes);
        $invoice->update(['settlement_status' => 'reversed']);
        $this->audit->log('branch_transfer_cash.reversed', $invoice, ['settlement_status' => 'settled'], ['settlement_status' => 'reversed'], $notes);
    }

    /** @return array{BranchCashAccount, BranchCashAccount} source first, destination second */
    private function lockedAccounts(int $sourceBranchId, int $destinationBranchId): array
    {
        $ids = collect([$sourceBranchId, $destinationBranchId])->sort()->values();
        $accounts = [];
        foreach ($ids as $branchId) {
            $accounts[$branchId] = $this->account((int) $branchId);
        }

        return [$accounts[$sourceBranchId], $accounts[$destinationBranchId]];
    }

    private function account(int $branchId): BranchCashAccount
    {
        $account = BranchCashAccount::query()->where('branch_id', $branchId)->lockForUpdate()->first();
        if ($account !== null) {
            return $account;
        }

        try {
            BranchCashAccount::query()->create(['branch_id' => $branchId, 'balance' => '0.00']);
        } catch (\Throwable) {
            // A concurrent acceptance may have created the account first.
        }

        return BranchCashAccount::query()->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
    }

    private function movement(BranchCashAccount $account, BranchCashAccount $counterparty, BranchTransferInvoice $invoice, string $type, int $amount, int $before, int $after, User $user, ?string $notes = null): void
    {
        BranchCashMovement::query()->create([
            'movement_number' => $this->nextMovementNumber(),
            'branch_cash_account_id' => $account->id,
            'branch_id' => $account->branch_id,
            'counterparty_branch_id' => $counterparty->branch_id,
            'book_transfer_id' => $invoice->book_transfer_id,
            'branch_transfer_invoice_id' => $invoice->id,
            'type' => $type,
            'amount' => Money::decimal($amount),
            'balance_before' => Money::decimal($before),
            'balance_after' => Money::decimal($after),
            'settled_on' => today(),
            'reference_number' => $invoice->invoice_number,
            'notes' => $notes,
            'recorded_by' => $user->id,
        ]);
    }

    private function nextMovementNumber(): string
    {
        do {
            $number = 'BCM-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (BranchCashMovement::query()->where('movement_number', $number)->exists());

        return $number;
    }
}
