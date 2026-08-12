<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Book;
use App\Models\SupplierPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SupplierPurchaseService
{
    public function __construct(private readonly InventoryService $inventory, private readonly AuditService $audit)
    {
    }

    public function create(array $data, User $user): SupplierPurchase
    {
        return DB::transaction(function () use ($data, $user): SupplierPurchase {
            $subtotal = 0;
            $discount = 0;
            $tax = 0;
            $lines = [];
            $books = [];

            foreach ($data['items'] as $row) {
                $book = Book::query()->lockForUpdate()->findOrFail($row['book_id']);
                $quantity = (int) $row['quantity_ordered'];
                $purchasePrice = Money::cents($row['unit_purchase_price']);
                $discountRate = Money::cents((string) ($row['discount_percentage'] ?? 0));
                $discountAmount = intdiv(($purchasePrice * $quantity * $discountRate) + 5000, 10000);
                $lineBase = $purchasePrice * $quantity - $discountAmount;
                $taxRate = Money::cents((string) ($row['tax_rate'] ?? 0));
                $lineTax = intdiv(($lineBase * $taxRate) + 5000, 10000);

                $book->update(['purchase_price' => Money::decimal($purchasePrice)]);
                $books[$book->id] = $book;
                $lines[] = [
                    'book_id' => $book->id,
                    'quantity_ordered' => $quantity,
                    'quantity_received' => $quantity,
                    'unit_price' => Money::decimal($purchasePrice),
                    'discount_amount' => Money::decimal($discountAmount),
                    'tax_rate' => $row['tax_rate'] ?? 0,
                    'line_total' => Money::decimal($lineBase + $lineTax),
                ];
                $subtotal += $purchasePrice * $quantity;
                $discount += $discountAmount;
                $tax += $lineTax;
            }

            $purchase = SupplierPurchase::create([
                'purchase_number' => 'PO-'.now()->format('Ymd').'-'.str_pad((string) ((SupplierPurchase::withoutGlobalScopes()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
                'supplier_id' => $data['supplier_id'],
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'subtotal' => Money::decimal($subtotal),
                'discount_total' => Money::decimal($discount),
                'tax_total' => Money::decimal($tax),
                'total' => Money::decimal($subtotal - $discount + $tax),
                'amount_paid' => '0.00',
                'payment_status' => 'unpaid',
                'status' => 'received',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $purchase->items()->create($line);
                $this->inventory->change(
                    $books[$line['book_id']],
                    $line['quantity_ordered'],
                    'supplier_receipt',
                    $purchase,
                    $purchase->academicYear,
                    $user,
                    'Supplier purchase received',
                );
            }

            $this->audit->log('supplier_purchase.received', $purchase, [], [
                'total' => $purchase->total,
                'items' => count($lines),
            ]);

            return $purchase;
        });
    }

    public function receiveOutstanding(SupplierPurchase $purchase, User $user): void
    {
        DB::transaction(function () use ($purchase, $user): void {
            $purchase = SupplierPurchase::query()->with('items.book', 'academicYear')->lockForUpdate()->findOrFail($purchase->id);

            foreach ($purchase->items as $item) {
                $remaining = $item->quantity_ordered - $item->quantity_received;
                if ($remaining < 1) {
                    continue;
                }

                $item->increment('quantity_received', $remaining);
                $this->inventory->change(
                    $item->book,
                    $remaining,
                    'supplier_receipt',
                    $purchase,
                    $purchase->academicYear,
                    $user,
                    'Outstanding supplier purchase received',
                );
            }

            $purchase->update(['status' => 'received']);
            $this->audit->log('supplier_purchase.received', $purchase, [], ['status' => 'received', 'legacy_completion' => true]);
        });
    }
}
