<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BookTransfer;
use App\Models\BranchTransferInvoice;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates the priced internal document for a branch-to-branch stock movement.
 * It intentionally does not create a student invoice, payment, receivable, or revenue.
 */
final class BranchTransferInvoiceService
{
    public function __construct(private readonly AuditService $audit) {}

    public function issue(BookTransfer $transfer, User $user): BranchTransferInvoice
    {
        if ($transfer->transfer_type !== 'branch_to_branch') {
            throw ValidationException::withMessages(['transfer' => 'Only branch-to-branch transfers can have an internal financial invoice.']);
        }

        $existing = $transfer->financialInvoice()->first();
        if ($existing !== null) {
            return $existing;
        }

        $transfer->loadMissing('items.book', 'sourceBranch', 'destinationBranch');
        if ($transfer->sourceBranch === null || $transfer->destinationBranch === null || $transfer->items->isEmpty()) {
            throw ValidationException::withMessages(['transfer' => 'A branch transfer needs two branches and at least one book before it can be valued.']);
        }

        $subtotal = 0;
        $taxTotal = 0;
        $lines = [];

        foreach ($transfer->items as $item) {
            $book = $item->book;
            if ($book === null) {
                throw ValidationException::withMessages(['transfer' => 'A transferred book could not be found for financial valuation.']);
            }

            $quantity = (int) $item->quantity;
            $unit = Money::cents((string) $book->selling_price);
            $lineSubtotal = $unit * $quantity;
            $taxRate = Money::cents((string) $book->tax_rate);
            $lineTax = intdiv(($lineSubtotal * $taxRate) + 5000, 10000);
            $lineTotal = $lineSubtotal + $lineTax;

            $lines[] = compact('book', 'quantity', 'unit', 'taxRate', 'lineSubtotal', 'lineTax', 'lineTotal');
            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        $invoice = BranchTransferInvoice::create([
            'book_transfer_id' => $transfer->id,
            'invoice_number' => $this->nextInvoiceNumber(),
            'source_branch_id' => $transfer->source_branch_id,
            'destination_branch_id' => $transfer->destination_branch_id,
            'issued_on' => $transfer->transferred_on,
            'subtotal' => Money::decimal($subtotal),
            'tax_total' => Money::decimal($taxTotal),
            'total' => Money::decimal($subtotal + $taxTotal),
            'status' => $transfer->status === 'pending_receipt' ? 'pending_acceptance' : 'issued',
            'settlement_status' => $transfer->status === 'pending_receipt' ? 'pending_acceptance' : 'legacy_unsettled',
            'notes' => $transfer->notes,
            'issued_by' => $user->id,
        ]);

        foreach ($lines as $line) {
            $invoice->items()->create([
                'book_id' => $line['book']->id,
                'description' => $line['book']->title,
                'sku' => $line['book']->sku,
                'quantity' => $line['quantity'],
                'unit_price' => Money::decimal($line['unit']),
                'tax_rate' => Money::decimal($line['taxRate']),
                'line_subtotal' => Money::decimal($line['lineSubtotal']),
                'line_tax' => Money::decimal($line['lineTax']),
                'line_total' => Money::decimal($line['lineTotal']),
            ]);
        }

        $this->audit->log('branch_transfer_invoice.dispatched', $invoice, [], [
            'transfer_number' => $transfer->transfer_number,
            'source_branch' => $transfer->sourceBranch->code,
            'destination_branch' => $transfer->destinationBranch->code,
            'total' => $invoice->total,
        ]);

        return $invoice;
    }

    public function acceptFor(BookTransfer $transfer, User $user, ?string $notes = null): BranchTransferInvoice
    {
        $invoice = $transfer->financialInvoice()->lockForUpdate()->first();
        if ($invoice === null) {
            throw ValidationException::withMessages(['transfer' => 'The internal transfer invoice could not be found.']);
        }
        if ($invoice->status !== 'pending_acceptance') {
            throw ValidationException::withMessages(['transfer' => 'This transfer invoice is no longer awaiting confirmation.']);
        }

        $invoice->update([
            'status' => 'accepted',
            'accepted_by' => $user->id,
            'accepted_at' => now(),
            'settlement_status' => 'pending_settlement',
            'notes' => $notes ?? $invoice->notes,
        ]);
        $this->audit->log('branch_transfer_invoice.accepted', $invoice, ['status' => 'pending_acceptance'], ['status' => 'accepted'], $notes);

        return $invoice->fresh();
    }

    public function rejectFor(BookTransfer $transfer, User $user, string $reason): void
    {
        $invoice = $transfer->financialInvoice()->lockForUpdate()->first();
        if ($invoice === null) {
            throw ValidationException::withMessages(['transfer' => 'The internal transfer invoice could not be found.']);
        }
        if ($invoice->status !== 'pending_acceptance') {
            throw ValidationException::withMessages(['transfer' => 'This transfer invoice is no longer awaiting confirmation.']);
        }

        $invoice->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'settlement_status' => 'not_settled',
        ]);
        $this->audit->log('branch_transfer_invoice.rejected', $invoice, ['status' => 'pending_acceptance'], ['status' => 'rejected'], $reason);
    }

    public function voidFor(BookTransfer $transfer, User $user, ?string $reason = null): void
    {
        $invoice = $transfer->financialInvoice()->lockForUpdate()->first();
        if ($invoice === null || $invoice->status === 'voided') {
            return;
        }

        $invoice->update([
            'status' => 'voided',
            'voided_at' => now(),
            'voided_by' => $user->id,
            'void_reason' => $reason,
            'settlement_status' => $invoice->settlement_status === 'reversed' ? 'reversed' : 'not_settled',
        ]);

        $this->audit->log('branch_transfer_invoice.voided', $invoice, ['status' => $invoice->getOriginal('status')], ['status' => 'voided'], $reason);
    }

    private function nextInvoiceNumber(): string
    {
        do {
            $number = 'BTI-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (BranchTransferInvoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }
}
