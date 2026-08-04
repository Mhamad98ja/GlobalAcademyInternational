<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Services\SupplierReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierReturnsController extends Controller
{
    public function create(SupplierPurchase $purchase): View
    {
        $purchase->load('supplier', 'items.book');
        $returnedQuantities = SupplierReturnItem::query()
            ->selectRaw('supplier_purchase_item_id, SUM(quantity) as returned_quantity')
            ->whereIn('supplier_purchase_item_id', $purchase->items->pluck('id'))
            ->groupBy('supplier_purchase_item_id')
            ->pluck('returned_quantity', 'supplier_purchase_item_id');

        $purchase->items->each(function ($item) use ($returnedQuantities): void {
            $remainingFromPurchase = max(0, $item->quantity_received - (int) ($returnedQuantities[$item->id] ?? 0));
            $availableStock = max(0, $item->book->stock_on_hand - $item->book->reserved_quantity);
            $item->setAttribute('returnable_quantity', min($remainingFromPurchase, $availableStock));
        });

        return view('supplier-returns.form', compact('purchase'));
    }

    public function store(Request $request, SupplierPurchase $purchase, SupplierReturnService $service): RedirectResponse
    {
        $data = $request->validate([
            'returned_on' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_purchase_item_id' => ['required', 'exists:supplier_purchase_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $return = $service->create($purchase, $data['items'], $data, $request->user());

        return redirect()->route('supplier-returns.show', $return)->with('success', 'Supplier return completed. Stock and the supplier credit were updated.');
    }

    public function show(SupplierReturn $supplierReturn): View
    {
        $supplierReturn->load('supplier', 'purchase', 'items.book', 'returnedBy');

        return view('supplier-returns.show', compact('supplierReturn'));
    }
}
