<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
final class PaymentService
{
    public function __construct(private readonly InvoiceService $invoices, private readonly AuditService $audit) {}
    public function record(Invoice $invoice, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($invoice,$data,$user): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === 'cancelled') throw ValidationException::withMessages(['invoice'=>'Cancelled invoices cannot receive payment.']);
            $this->invoices->refreshBalances($invoice);
            $amount = Money::cents($data['amount']);
            if ($amount <= 0 || $amount > Money::cents((string)$invoice->balance_due)) throw ValidationException::withMessages(['amount'=>'Payment must be greater than zero and no more than the balance due.']);
            $payment = Payment::create(['payment_number'=>'PAY-'.now()->format('Ymd').'-'.str_pad((string)((Payment::max('id') ?? 0)+1),5,'0',STR_PAD_LEFT),'invoice_id'=>$invoice->id,'student_id'=>$invoice->student_id,'payment_date'=>$data['payment_date'],'amount'=>Money::decimal($amount),'method'=>$data['method'],'cashbox'=>$data['cashbox'] ?? null,'reference_number'=>$data['reference_number'] ?? null,'status'=>'valid','idempotency_key'=>$data['idempotency_key'] ?? (string)Str::uuid(),'notes'=>$data['notes'] ?? null,'received_by'=>$user->id]);
            $this->invoices->refreshBalances($invoice);
            $this->audit->log('payment.recorded',$payment,[],['amount'=>$payment->amount]);
            return $payment;
        });
    }
    public function reverse(Payment $payment, string $reason, User $user): PaymentReversal
    {
        return DB::transaction(function () use ($payment,$reason,$user): PaymentReversal {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'valid' || $payment->reversal()->exists()) throw ValidationException::withMessages(['payment'=>'This payment has already been reversed.']);
            $reversal=PaymentReversal::create(['payment_id'=>$payment->id,'amount'=>$payment->amount,'reason'=>$reason,'approved_by'=>$user->id,'reversed_by'=>$user->id,'reversed_at'=>now()]);
            $payment->update(['status'=>'reversed']); $this->invoices->refreshBalances($payment->invoice); $this->audit->log('payment.reversed',$payment,['status'=>'valid'],['status'=>'reversed'],$reason);
            return $reversal;
        });
    }
}
