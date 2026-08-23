<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\BookDistributionItem;
use App\Models\BookReturn;
use App\Models\BookReturnItem;
use App\Models\Invoice;
use App\Services\BookReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class ReturnsController extends Controller
{
 public function index():View{return view('returns.index',['returns'=>BookReturn::with('student','invoice')->latest('returned_on')->paginate(15),'invoices'=>Invoice::with('student')->where('status','!=','cancelled')->latest()->take(100)->get()]);}
 public function invoiceItems(Invoice $invoice):View{$invoice->load('student','items.book');$items=BookDistributionItem::query()->with('book','distribution')->whereHas('distribution',fn($q)=>$q->where('invoice_id',$invoice->id))->get();$returned=BookReturnItem::query()->selectRaw('book_distribution_item_id, SUM(quantity) as returned_quantity')->whereIn('book_distribution_item_id',$items->pluck('id'))->groupBy('book_distribution_item_id')->pluck('returned_quantity','book_distribution_item_id');$items=$items->map(function(BookDistributionItem $item)use($returned):BookDistributionItem{$item->setAttribute('returnable_quantity',max(0,$item->quantity-(int)($returned[$item->id]??0)));return $item;})->filter(fn(BookDistributionItem $item)=>(int)$item->returnable_quantity>0)->values();return view('returns.form',compact('invoice','items'));}
 public function store(Request $request,Invoice $invoice,BookReturnService $service):RedirectResponse{$data=$request->validate(['notes'=>['nullable','string','max:3000'],'items'=>['required','array','min:1'],'items.*.distribution_item_id'=>['required','exists:book_distribution_items,id'],'items.*.quantity'=>['required','integer','min:1'],'items.*.condition'=>['required','in:new_resellable,used_acceptable,damaged,lost,not_eligible'],'items.*.reason'=>['required','string','max:255']]);$return=$service->accept($invoice,$data['items'],$request->user(),$data['notes']??null);return redirect()->route('returns.show',$return)->with('success','Book return accepted.');}
 public function show(BookReturn $return):View{$return->load('student','invoice','items.book');return view('returns.show',compact('return'));}
}
