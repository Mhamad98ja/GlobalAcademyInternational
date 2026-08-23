<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeferredPaymentApproval;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeferredPaymentApprovalService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly InvoiceService $invoices,
        private readonly DeferredPaymentNotificationService $notifications,
    ) {
    }

    /** @param array{expected_payment_date?: string|null, reason?: string|null, reference_number?: string|null, notes?: string|null, document_path?: string|null} $data */
    public function requestForInvoice(Invoice $invoice, User $user, array $data): DeferredPaymentApproval
    {
        return DB::transaction(function () use ($invoice, $user, $data): DeferredPaymentApproval {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === 'cancelled' || Money::cents((string) $invoice->balance_due) < 1) {
                throw ValidationException::withMessages(['invoice_id' => 'Only an unpaid invoice can be submitted for deferred payment approval.']);
            }
            if (DeferredPaymentApproval::query()->where('invoice_id', $invoice->id)->whereIn('status', ['pending', 'approved'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['invoice_id' => 'This invoice already has an active deferred-payment request.']);
            }

            $approval = DeferredPaymentApproval::create([
                'student_id' => $invoice->student_id,
                'student_registration_id' => $invoice->student_registration_id,
                'invoice_id' => $invoice->id,
                'amount_deferred' => $invoice->balance_due,
                'expected_payment_date' => $data['expected_payment_date'] ?? null,
                'reason' => $data['reason'] ?? 'Deferred payment requested during book distribution.',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'document_path' => $data['document_path'] ?? null,
                'status' => 'pending',
                'follow_up_status' => 'awaiting_branch_decision',
                'created_by' => $user->id,
            ]);
            $invoice->update([
                'status' => 'pending_deferred_approval',
                'payment_status' => Money::cents((string) $invoice->amount_paid) > 0 ? 'partially_paid' : 'unpaid',
            ]);
            $this->audit->log('deferred_payment.requested', $approval, [], [
                'invoice_number' => $invoice->invoice_number,
                'amount_deferred' => $approval->amount_deferred,
            ]);
            DB::afterCommit(fn () => $this->notifications->requested($approval));

            return $approval;
        });
    }

    public function decide(DeferredPaymentApproval $approval, User $administrator, string $decision, ?string $notes = null): DeferredPaymentApproval
    {
        return DB::transaction(function () use ($approval, $administrator, $decision, $notes): DeferredPaymentApproval {
            // Payment posting locks the invoice first, so take locks in the same order here.
            // That keeps a branch decision and a payment from deadlocking each other.
            $invoiceId = DeferredPaymentApproval::query()->whereKey($approval->id)->value('invoice_id');
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);
            $approval = DeferredPaymentApproval::query()->lockForUpdate()->findOrFail($approval->id);
            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['approval' => 'This deferred-payment request has already received a decision.']);
            }

            $approval->update([
                'status' => $decision,
                'administrator_id' => $administrator->id,
                'approval_date' => today(),
                'decision_notes' => $notes,
                'follow_up_status' => $decision === 'approved' ? $this->followUpStatus($invoice) : 'payment_required',
            ]);
            if ($decision === 'approved') {
                $invoice->update(['status' => 'deferred']);
            } else {
                $invoice->update(['status' => 'issued']);
            }
            $invoice = $this->invoices->refreshBalances($invoice);
            if ($decision === 'approved') {
                $approval->update(['follow_up_status' => $this->followUpStatus($invoice)]);
            }
            $this->audit->log('deferred_payment.'.$decision, $approval, ['status' => 'pending'], [
                'status' => $decision,
                'remaining_balance' => $invoice->balance_due,
            ], $notes);
            DB::afterCommit(fn () => $this->notifications->decided($approval));

            return $approval;
        });
    }

    public function syncForInvoice(Invoice $invoice): void
    {
        $approval = DeferredPaymentApproval::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'approved')
            ->lockForUpdate()
            ->latest('id')
            ->first();
        if ($approval === null) {
            return;
        }

        $followUpStatus = $this->followUpStatus($invoice);
        if ($approval->follow_up_status !== $followUpStatus) {
            $approval->update(['follow_up_status' => $followUpStatus]);
            $this->audit->log('deferred_payment.balance_updated', $approval, [], [
                'remaining_balance' => $invoice->balance_due,
                'follow_up_status' => $followUpStatus,
            ]);
        }
    }

    private function followUpStatus(Invoice $invoice): string
    {
        if (Money::cents((string) $invoice->balance_due) === 0) {
            return 'settled';
        }

        return Money::cents((string) $invoice->amount_paid) > 0 ? 'partially_paid' : 'payment_required';
    }
}
