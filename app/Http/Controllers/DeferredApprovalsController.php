<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\DeferredPaymentApproval;
use App\Models\Invoice;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
class DeferredApprovalsController extends Controller
{
 public function index():View{return view('deferred.index',['approvals'=>DeferredPaymentApproval::with('student','invoice')->latest()->paginate(15),'invoices'=>Invoice::with('student')->where('balance_due','>',0)->where('status','!=','cancelled')->latest()->take(100)->get()]);}
 public function store(Request $request,AuditService $audit):RedirectResponse{$data=$request->validate(['invoice_id'=>['required','exists:invoices,id'],'amount_deferred'=>['required','decimal:0,2','min:0.01'],'expected_payment_date'=>['nullable','date','after_or_equal:today'],'reason'=>['required','string','max:500'],'reference_number'=>['nullable','string','max:100'],'notes'=>['nullable','string','max:3000'],'document'=>['nullable','file','mimetypes:application/pdf,image/jpeg,image/png','max:5120']]);$invoice=Invoice::findOrFail($data['invoice_id']);$path=$request->file('document')?->store('private/deferred-approvals');$approval=DeferredPaymentApproval::create(['student_id'=>$invoice->student_id,'student_registration_id'=>$invoice->student_registration_id,'invoice_id'=>$invoice->id,'amount_deferred'=>$data['amount_deferred'],'expected_payment_date'=>$data['expected_payment_date']??null,'reason'=>$data['reason'],'reference_number'=>$data['reference_number']??null,'notes'=>$data['notes']??null,'document_path'=>$path,'status'=>'pending','follow_up_status'=>'open','created_by'=>$request->user()->id]);$audit->log('deferred_payment.requested',$approval);return back()->with('success','Deferred-payment request submitted for approval.');}
 public function approve(Request $request,DeferredPaymentApproval $approval,AuditService $audit):RedirectResponse{$data=$request->validate(['decision'=>['required','in:approved,rejected'],'notes'=>['nullable','string','max:3000']]);$approval->update(['status'=>$data['decision'],'administrator_id'=>$request->user()->id,'approval_date'=>today(),'notes'=>$data['notes']??$approval->notes]);if($data['decision']==='approved')$approval->invoice->update(['status'=>'deferred','payment_status'=>'deferred']);$audit->log('deferred_payment.'.$data['decision'],$approval);return back()->with('success','Deferred-payment request '.$data['decision'].'.');}
 public function document(DeferredPaymentApproval $approval){abort_unless(auth()->user()?->hasPermission('deferred.view'),403);abort_unless($approval->document_path && Storage::exists($approval->document_path),404);return Storage::download($approval->document_path);}
}
