<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookReturn;
use App\Models\Invoice;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();
        $search = trim($request->string('search')->toString());
        $user = $request->user();
        $canViewPurchases = $user->hasPermission('purchases.manage');
        $canViewInvoices = $user->hasPermission('invoices.view');
        $canViewReturns = $user->hasPermission('returns.manage');
        $canViewSupplierReturns = $canViewPurchases;
        $records = collect();

        if ($canViewPurchases && in_array($type, ['', 'purchase'], true)) {
            $records = $records->merge(
                SupplierPurchase::query()
                    ->with('supplier')
                    ->withSum('supplierReturns as supplier_returns_credit_total_sum', 'credit_total')
                    ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                        $query->where('purchase_number', 'like', '%'.$search.'%')
                            ->orWhere('supplier_invoice_number', 'like', '%'.$search.'%')
                            ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                    }))
                    ->latest('purchase_date')
                    ->take(100)
                    ->get()
                    ->map(fn (SupplierPurchase $purchase) => [
                        'type' => 'purchase',
                        'type_label' => 'Supplier purchase',
                        'reference' => $purchase->purchase_number,
                        'detail' => $purchase->supplier_invoice_number ? 'Supplier invoice: '.$purchase->supplier_invoice_number : 'No supplier invoice number',
                        'party' => $purchase->supplier->name,
                        'date' => $purchase->purchase_date,
                        'total' => $purchase->total,
                        'balance' => $purchase->balance_due,
                        'status' => $purchase->payment_status,
                        'href' => route('purchases.show', $purchase),
                    ])
            );
        }

        if ($canViewSupplierReturns && in_array($type, ['', 'supplier_return'], true)) {
            $records = $records->merge(
                SupplierReturn::query()
                    ->with('supplier', 'purchase')
                    ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                        $query->where('reference_number', 'like', '%'.$search.'%')
                            ->orWhereHas('purchase', fn ($purchase) => $purchase->where('purchase_number', 'like', '%'.$search.'%'))
                            ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                    }))
                    ->latest('returned_on')
                    ->take(100)
                    ->get()
                    ->map(fn (SupplierReturn $return) => [
                        'type' => 'supplier_return',
                        'type_label' => 'Supplier return',
                        'reference' => $return->reference_number ?: 'Supplier return #'.$return->id,
                        'detail' => 'For purchase '.$return->purchase->purchase_number,
                        'party' => $return->supplier->name,
                        'date' => $return->returned_on,
                        'total' => $return->credit_total,
                        'balance' => null,
                        'status' => $return->status,
                        'href' => route('supplier-returns.show', $return),
                    ])
            );
        }

        if ($canViewInvoices && in_array($type, ['', 'invoice'], true)) {
            $records = $records->merge(
                Invoice::query()
                    ->with('student')
                    ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                        $query->where('invoice_number', 'like', '%'.$search.'%')
                            ->orWhereHas('student', fn ($student) => $student->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                    }))
                    ->latest('invoice_date')
                    ->take(100)
                    ->get()
                    ->map(fn (Invoice $invoice) => [
                        'type' => 'invoice',
                        'type_label' => 'Student invoice',
                        'reference' => $invoice->invoice_number,
                        'detail' => $invoice->status === 'cancelled' ? 'Cancelled invoice' : 'Books distributed to student',
                        'party' => $invoice->student->full_name,
                        'date' => $invoice->invoice_date,
                        'total' => $invoice->total,
                        'balance' => $invoice->balance_due,
                        'status' => $invoice->payment_status,
                        'href' => route('invoices.show', $invoice),
                    ])
            );
        }

        if ($canViewReturns && in_array($type, ['', 'return'], true)) {
            $records = $records->merge(
                BookReturn::query()
                    ->with('student', 'invoice')
                    ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                        $query->whereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', '%'.$search.'%'))
                            ->orWhereHas('student', fn ($student) => $student->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                    }))
                    ->latest('returned_on')
                    ->take(100)
                    ->get()
                    ->map(fn (BookReturn $return) => [
                        'type' => 'return',
                        'type_label' => 'Book return',
                        'reference' => 'Return #'.$return->id,
                        'detail' => 'For invoice '.$return->invoice->invoice_number,
                        'party' => $return->student->full_name,
                        'date' => $return->returned_on,
                        'total' => $return->refund_total,
                        'balance' => null,
                        'status' => $return->status,
                        'href' => route('returns.show', $return),
                    ])
            );
        }

        $records = $records->sortByDesc(fn (array $record) => $record['date']->format('Y-m-d'))->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $transactions = new LengthAwarePaginator(
            $records->forPage($page, 25)->values(),
            $records->count(),
            25,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('operations.index', compact('transactions', 'type', 'search', 'canViewPurchases', 'canViewInvoices', 'canViewReturns', 'canViewSupplierReturns'));
    }
}
