<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\Branch;
use App\Models\BranchBookStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryService
{
    public function __construct(private readonly BranchContext $branches)
    {
    }

    /**
     * Adjusts stock owned by one branch. The catalogue quantity remains an aggregate
     * for compatibility; all availability checks are made against the branch balance.
     */
    public function change(
        Book|int $book,
        int $quantity,
        string $type,
        ?Model $reference,
        ?AcademicYear $year,
        ?User $user,
        string $reason,
        ?string $notes = null,
        bool $allowNegative = false,
        ?Branch $branch = null,
        ?User $holder = null,
    ): Book {
        $branchId = $branch?->id ?? $this->branches->id();
        if ($branchId === null) {
            throw ValidationException::withMessages(['branch' => 'Select an active branch before changing stock.']);
        }

        $bookId = $book instanceof Book ? $book->id : $book;
        $locked = Book::query()->lockForUpdate()->findOrFail($bookId);
        $stock = BranchBookStock::query()->where('branch_id', $branchId)->where('book_id', $locked->id)->lockForUpdate()->first();
        if (! $stock) {
            try {
                BranchBookStock::query()->create(['branch_id' => $branchId, 'book_id' => $locked->id]);
            } catch (\Throwable) {
                // A concurrent transaction may have created the unique branch/book record.
            }
            $stock = BranchBookStock::query()->where('branch_id', $branchId)->where('book_id', $locked->id)->lockForUpdate()->firstOrFail();
        }

        $before = (int) $stock->quantity_on_hand;
        $after = $before + $quantity;
        $held = (int) DB::table('user_book_stocks')->where('branch_id', $branchId)->where('book_id', $locked->id)->sum('quantity_on_hand');
        $available = $before - (int) $stock->reserved_quantity - $held;
        if ($quantity < 0 && -$quantity > $available && ! $allowNegative) {
            throw ValidationException::withMessages([
                'items' => "Insufficient stock for {$locked->title} at {$stock->branch->name}. Available: {$available}.",
            ]);
        }

        $stock->quantity_on_hand = $after;
        $stock->save();

        // Keep this legacy field as the all-branches total so reports and older data stay valid.
        $locked->stock_on_hand = (int) $locked->stock_on_hand + $quantity;
        $locked->save();

        StockMovement::withoutGlobalScope('branch')->create([
            'branch_id' => $branchId,
            'book_id' => $locked->id,
            'type' => $type,
            'quantity_before' => $before,
            'quantity_change' => $quantity,
            'quantity_after' => $after,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'academic_year_id' => $year?->id,
            'user_id' => $user?->id,
            'holder_user_id' => $holder?->id,
            'reason' => $reason,
            'notes' => $notes,
        ]);

        return $locked;
    }

    public function balance(Book|int $book, ?Branch $branch = null): BranchBookStock
    {
        $branchId = $branch?->id ?? $this->branches->id();
        abort_unless($branchId !== null, 422, 'An active branch is required.');
        $bookId = $book instanceof Book ? $book->id : $book;

        return BranchBookStock::query()->firstOrCreate(['branch_id' => $branchId, 'book_id' => $bookId]);
    }

    public function availableForCustody(Book $book, Branch $branch): int
    {
        $stock = BranchBookStock::query()->where('branch_id', $branch->id)->where('book_id', $book->id)->lockForUpdate()->first();
        if (! $stock) {
            $stock = BranchBookStock::query()->create(['branch_id' => $branch->id, 'book_id' => $book->id]);
            $stock = BranchBookStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
        }

        return (int) $stock->quantity_on_hand - (int) $stock->reserved_quantity - (int) DB::table('user_book_stocks')->where('branch_id', $branch->id)->where('book_id', $book->id)->sum('quantity_on_hand');
    }
}
