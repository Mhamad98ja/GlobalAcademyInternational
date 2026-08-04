<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SupplierPurchase;
use App\Models\SupplierPurchaseItem;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplierReturnService
{
    public function __construct(private readonly InventoryService $inventory, private readonly AuditService $audit)
    {
    }

    public function create(SupplierPurchase $purchase, array $items, array $data, User $user): SupplierReturn
    {
        return DB::transaction(function () use ($purchase, $items, $data, $user): SupplierReturn {
            $purchase = SupplierPurchase::query()->with('academicYear')->lockForUpdate()->findOrFail($purchase->id);
            $lines = collect($items)->filter(fn (array $item) => (int) ($item['quantity'] ?? 0) > 0)->values();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Select at least one book quantity to return to the supplier.']);
            }

            $return = SupplierReturn::create([
                'supplier_purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'academic_year_id' => $purchase->academic_year_id,
                'returned_on' => $data['returned_on'],
                'credit_total' => '0.00',
                'reference_number' => $data['reference_number'] ?? null,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'returned_by' => $user->id,
            ]);

            $creditTotal = 0;
            foreach ($lines as $row) {
                $item = SupplierPurchaseItem::query()->with('book')->lockForUpdate()->findOrFail($row['supplier_purchase_item_id']);
                if ($item->supplier_purchase_id !== $purchase->id) {
                    throw ValidationException::withMessages(['items' => 'A selected item does not belong to this supplier purchase.']);
                }

                $quantity = (int) $row['quantity'];
                $reason = trim((string) ($row['reason'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages(['items' => 'Enter a reason for every returned book.']);
                }

                $previousQuantity = (int) SupplierReturnItem::query()->where('supplier_purchase_item_id', $item->id)->sum('quantity');
                $returnableQuantity = $item->quantity_received - $previousQuantity;
                if ($quantity < 1 || $quantity > $returnableQuantity) {
                    throw ValidationException::withMessages(['items' => "Return quantity for {$item->book->title} exceeds the quantity received from this supplier."]);
                }

                $lineTotal = Money::cents((string) $item->line_total);
                $credit = $this->proratedCredit($lineTotal, $item->quantity_ordered, $previousQuantity + $quantity)
                    - $this->proratedCredit($lineTotal, $item->quantity_ordered, $previousQuantity);

                $return->items()->create([
                    'supplier_purchase_item_id' => $item->id,
                    'book_id' => $item->book_id,
                    'quantity' => $quantity,
                    'credit_total' => Money::decimal($credit),
                    'reason' => $reason,
                ]);
                $this->inventory->change(
                    $item->book,
                    -$quantity,
                    'supplier_return',
                    $return,
                    $purchase->academicYear,
                    $user,
                    'Returned to supplier',
                    $data['notes'] ?? null,
                );
                $creditTotal += $credit;
            }

            $return->update(['credit_total' => Money::decimal($creditTotal)]);
            $this->audit->log('supplier_return.completed', $return, [], [
                'purchase_number' => $purchase->purchase_number,
                'credit_total' => $return->credit_total,
                'items' => $lines->count(),
            ]);

            return $return;
        });
    }

    private function proratedCredit(int $lineTotal, int $sourceQuantity, int $returnQuantity): int
    {
        return intdiv(($lineTotal * $returnQuantity) + intdiv($sourceQuantity, 2), $sourceQuantity);
    }
}
