<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SupplierPayment;
use App\Models\SupplierPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplierPaymentService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function record(SupplierPurchase $purchase, array $data, User $user): SupplierPayment
    {
        return DB::transaction(function () use ($purchase, $data, $user): SupplierPayment {
            $purchase = SupplierPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $amount = Money::cents($data['amount']);
            $total = Money::cents((string) $purchase->total);
            $paid = Money::cents((string) $purchase->amount_paid);
            $balance = max(0, $total - $paid);

            if ($amount <= 0 || $amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment must be greater than zero and no more than the remaining supplier balance.',
                ]);
            }

            $payment = $purchase->payments()->create([
                'amount' => Money::decimal($amount),
                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference_number' => $data['reference_number'] ?? null,
                'received_by' => $user->id,
            ]);

            $newPaid = $paid + $amount;
            $newBalance = $total - $newPaid;
            $purchase->update([
                'amount_paid' => Money::decimal($newPaid),
                'payment_status' => $newBalance === 0 ? 'paid' : 'partially_paid',
            ]);

            $this->audit->log('supplier_payment.recorded', $payment, [], [
                'purchase_number' => $purchase->purchase_number,
                'amount' => $payment->amount,
                'reference_number' => $payment->reference_number,
            ]);

            return $payment;
        });
    }
}
