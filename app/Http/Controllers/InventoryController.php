<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class InventoryController extends Controller
{
 public function ledger(Request $request):View{$movements=StockMovement::with('book','user')->when($request->filled('book_id'),fn($q)=>$q->where('book_id',$request->book_id))->latest()->paginate(25)->withQueryString();return view('inventory.ledger',['movements'=>$movements,'books'=>Book::active()->orderBy('title')->get()]);}
 public function adjustments():View{return view('inventory.adjustments',['books'=>Book::active()->orderBy('title')->get(),'adjustments'=>StockAdjustment::with('book')->latest()->paginate(10)]);}
 public function storeAdjustment(Request $request,InventoryService $inventory,AuditService $audit):RedirectResponse{$data=$request->validate(['book_id'=>['required','exists:books,id'],'quantity_change'=>['required','integer','not_in:0','min:-10000','max:10000'],'reason'=>['required','string','max:255'],'notes'=>['nullable','string','max:3000'],'academic_year_id'=>['nullable','exists:academic_years,id']]);DB::transaction(function()use($data,$inventory,$audit,$request):void{$adjustment=StockAdjustment::create(['book_id'=>$data['book_id'],'quantity_change'=>$data['quantity_change'],'reason'=>$data['reason'],'notes'=>$data['notes']??null,'approved_by'=>$request->user()->id,'created_by'=>$request->user()->id]);$inventory->change($data['book_id'],$data['quantity_change'],'manual_adjustment',$adjustment,isset($data['academic_year_id'])?AcademicYear::find($data['academic_year_id']):null,$request->user(),$data['reason'],$data['notes']??null,$request->user()->hasPermission('stock.allow-negative'));$audit->log('stock.adjusted',$adjustment,[],['quantity_change'=>$data['quantity_change']],$data['reason']);});return back()->with('success','Stock adjustment recorded in the inventory ledger.');}
}
