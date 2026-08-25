<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
final class InvoiceService
{
    public function refreshBalances(Invoice $invoice): Invoice
    {
        $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
        $paid = Payment::query()->where('invoice_id',$invoice->id)->where('status','valid')->sum('amount');
        $refunds = DB::table('refunds')->where('invoice_id',$invoice->id)->sum('amount');
        $paidCents = Money::cents((string)$paid) - Money::cents((string)$refunds);
        $balance = max(0, Money::cents((string)$invoice->total) - $paidCents);
        $invoice->amount_paid = Money::decimal($paidCents);
        $invoice->balance_due = Money::decimal($balance);
        $invoice->payment_status = $balance === 0 ? 'paid' : ($paidCents > 0 ? 'partially_paid' : 'unpaid');
        $invoice->save();
        return $invoice;
    }
}
