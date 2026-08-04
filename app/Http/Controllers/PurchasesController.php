<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Services\SupplierPaymentService;
use App\Services\SupplierPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchasesController extends Controller
{
    public function index(): View
    {
        return view('purchases.index', [
            'purchases' => SupplierPurchase::with('supplier')->withSum('supplierReturns as supplier_returns_credit_total_sum', 'credit_total')->latest('purchase_date')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('purchases.form', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'books' => Book::active()->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request, SupplierPurchaseService $service): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'purchase_date' => ['required', 'date'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'distinct', 'exists:books,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_purchase_price' => ['required', 'decimal:0,2', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
        ]);

        $purchase = $service->create($data, $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase saved, stock received, and catalogue purchase prices updated.');
    }

    public function show(SupplierPurchase $purchase): View
    {
        $purchase->load('supplier', 'items.book', 'items.supplierReturnItems', 'payments.receivedBy', 'supplierReturns.items.book');

        return view('purchases.show', compact('purchase'));
    }

    public function storePayment(Request $request, SupplierPurchase $purchase, SupplierPaymentService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'paid_on' => ['required', 'date'],
            'method' => ['required', 'in:cash,card,bank_transfer,check,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ]);

        $service->record($purchase, $data, $request->user());

        return back()->with('success', 'Supplier payment recorded. The remaining balance was updated.');
    }

    public function receiveOutstanding(Request $request, SupplierPurchase $purchase, SupplierPurchaseService $service): RedirectResponse
    {
        $service->receiveOutstanding($purchase, $request->user());

        return back()->with('success', 'All outstanding quantities were received into stock.');
    }
}
