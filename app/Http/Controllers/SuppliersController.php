<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class SuppliersController extends Controller
{
 public function index(Request $request):View{$suppliers=Supplier::query()->withSum('purchases as purchases_total_sum','total')->withSum('purchases as purchases_paid_sum','amount_paid')->withSum('supplierReturns as supplier_returns_credit_total_sum','credit_total')->when($request->filled('search'),fn($q)=>$q->where('name','like','%'.$request->search.'%')->orWhere('code','like','%'.$request->search.'%')->orWhere('phone','like','%'.$request->search.'%'))->orderBy('name')->paginate(15)->withQueryString();return view('suppliers.index',compact('suppliers'));}
 public function create():View{return view('suppliers.form',['supplier'=>new Supplier()]);}
 public function store(StoreSupplierRequest $request):RedirectResponse{$data=$request->validated();$data['is_active']=$request->boolean('is_active',true);$supplier=Supplier::create($data);return redirect()->route('suppliers.show',$supplier)->with('success','Supplier added.');}
 public function show(Supplier $supplier):View{$supplier->load(['purchases'=>fn($query)=>$query->withSum('supplierReturns as supplier_returns_credit_total_sum','credit_total')->latest('purchase_date'),'supplierReturns'=>fn($query)=>$query->latest('returned_on')]);return view('suppliers.show',compact('supplier'));}
 public function edit(Supplier $supplier):View{return view('suppliers.form',compact('supplier'));}
 public function update(StoreSupplierRequest $request,Supplier $supplier):RedirectResponse{$data=$request->validated();$data['is_active']=$request->boolean('is_active');$supplier->update($data);return redirect()->route('suppliers.show',$supplier)->with('success','Supplier updated.');}
 public function destroy(Supplier $supplier,AuditService $audit):RedirectResponse{$supplier->delete();$audit->log('supplier.deleted',$supplier);return redirect()->route('suppliers.index')->with('success','Supplier moved to recycle bin.');}
}
