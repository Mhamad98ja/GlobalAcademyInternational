<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\Invoice;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class InvoicesController extends Controller
{
 public function index(Request $request):View{$invoices=Invoice::query()->with('student')->when($request->filled('status'),fn($q)=>$q->where('payment_status',$request->status))->when($request->filled('search'),fn($q)=>$q->where(fn($invoices)=>$invoices->whereInsensitiveLike('invoice_number',$request->search)->orWhereHas('student',fn($s)=>$s->whereInsensitiveLike('first_name',$request->search)->orWhereInsensitiveLike('last_name',$request->search))))->latest('invoice_date')->paginate(15)->withQueryString();return view('invoices.index',compact('invoices'));}
 public function show(Invoice $invoice):View{$invoice->load('student','registration.grade','registration.section','items.book','payments.reversal','responsiblePerson');return view('invoices.show',compact('invoice'));}
 public function cancel(Request $request,Invoice $invoice,AuditService $audit):RedirectResponse{$data=$request->validate(['reason'=>['required','string','max:500']]);DB::transaction(function()use($invoice,$data,$audit,$request):void{$invoice=Invoice::query()->lockForUpdate()->findOrFail($invoice->id);abort_if($invoice->payments()->where('status','valid')->exists(),422,'Reverse valid payments before cancelling an invoice.');$invoice->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>$request->user()->id,'cancellation_reason'=>$data['reason']]);$audit->log('invoice.cancelled',$invoice,['status'=>'issued'],['status'=>'cancelled'],$data['reason']);});return back()->with('success','Invoice cancelled. Inventory is not changed; use the return workflow for distributed books.');}
 public function print(Request $request,Invoice $invoice):View{$copy=$request->string('copy')->toString()==='student'?'student':'financial';$invoice->load('student','registration.academicYear','registration.grade','registration.section','items.book','payments','responsiblePerson','createdBy');$school=SystemSetting::query()->whereIn('key',['school_name','school_address','school_phone'])->pluck('value','key');return view('invoices.print',compact('invoice','school','copy'));}
}
