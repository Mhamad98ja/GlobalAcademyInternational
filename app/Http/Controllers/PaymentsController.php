<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class PaymentsController extends Controller
{
 public function index():View{return view('payments.index',['payments'=>Payment::with('invoice','student')->latest('payment_date')->paginate(15)]);}
 public function store(StorePaymentRequest $request,Invoice $invoice,PaymentService $service):RedirectResponse{$payment=$service->record($invoice,$request->validated(),$request->user());return redirect()->route('payments.receipt',$payment)->with('success','Payment recorded.');}
 public function reverse(Request $request,Payment $payment,PaymentService $service):RedirectResponse{$data=$request->validate(['reason'=>['required','string','max:500']]);$service->reverse($payment,$data['reason'],$request->user());return redirect()->route('invoices.show',$payment->invoice)->with('success','Payment reversed and balance recalculated.');}
 public function receipt(Payment $payment):View{$payment->load('student','invoice');return view('payments.receipt',compact('payment'));}
}
