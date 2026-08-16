<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookTransfer;
use App\Models\Branch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserBookStock;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BookTransferService
{
    public function __construct(private readonly InventoryService $inventory, private readonly AuditService $audit)
    {
    }

    public function betweenBranches(Branch $source, Branch $destination, array $items, ?AcademicYear $year, User $user, ?string $notes = null): BookTransfer
    {
        if ($source->is($destination)) {
            throw ValidationException::withMessages(['destination_branch_id' => 'Choose a different destination branch.']);
        }

        return DB::transaction(function () use ($source, $destination, $items, $year, $user, $notes): BookTransfer {
            $transfer = $this->makeTransfer('branch_to_branch', $source, $destination, null, null, $year, $user, $notes);
            foreach ($this->normalise($items) as $line) {
                $book = Book::query()->findOrFail($line['book_id']);
                $this->inventory->change($book, -$line['quantity'], 'branch_transfer_out', $transfer, $year, $user, 'Transfer to '.$destination->name, $notes, false, $source);
                $this->inventory->change($book, $line['quantity'], 'branch_transfer_in', $transfer, $year, $user, 'Transfer from '.$source->name, $notes, false, $destination);
                $transfer->items()->create($line);
            }
            $this->audit->log('book_transfer.branch_completed', $transfer, [], ['source' => $source->code, 'destination' => $destination->code, 'items' => $transfer->items()->count()]);

            return $transfer;
        });
    }

    public function betweenUsers(Branch $branch, User $source, User $destination, array $items, ?AcademicYear $year, User $performedBy, ?string $notes = null): BookTransfer
    {
        if ($source->is($destination)) {
            throw ValidationException::withMessages(['destination_user_id' => 'Choose a different destination user.']);
        }
        if (! $source->branches()->whereKey($branch->id)->exists() || ! $destination->branches()->whereKey($branch->id)->exists()) {
            throw ValidationException::withMessages(['destination_user_id' => 'Both users must be assigned to the selected branch.']);
        }

        return DB::transaction(function () use ($branch, $source, $destination, $items, $year, $performedBy, $notes): BookTransfer {
            $transfer = $this->makeTransfer('user_to_user', $branch, $branch, $source, $destination, $year, $performedBy, $notes);
            foreach ($this->normalise($items) as $line) {
                $book = Book::query()->findOrFail($line['book_id']);
                $from = $this->custody($branch, $source, $book, true);
                if ($from->quantity_on_hand < $line['quantity']) {
                    throw ValidationException::withMessages(['items' => "{$source->name} does not hold enough copies of {$book->title}."]);
                }
                $to = $this->custody($branch, $destination, $book, true);
                $from->decrement('quantity_on_hand', $line['quantity']);
                $to->increment('quantity_on_hand', $line['quantity']);
                $transfer->items()->create($line);
                $this->recordCustodyMovement($book, (int) $from->quantity_on_hand + $line['quantity'], -$line['quantity'], $transfer, $year, $performedBy, $branch, $source, 'User transfer to '.$destination->name, $notes);
                $this->recordCustodyMovement($book, (int) $to->quantity_on_hand - $line['quantity'], $line['quantity'], $transfer, $year, $performedBy, $branch, $destination, 'User transfer from '.$source->name, $notes);
            }
            $this->audit->log('book_transfer.user_completed', $transfer, [], ['branch' => $branch->code, 'source_user_id' => $source->id, 'destination_user_id' => $destination->id, 'items' => $transfer->items()->count()]);

            return $transfer;
        });
    }

    public function assignToUser(Branch $branch, User $destination, array $items, ?AcademicYear $year, User $performedBy, ?string $notes = null): BookTransfer
    {
        if (! $destination->branches()->whereKey($branch->id)->exists()) {
            throw ValidationException::withMessages(['destination_user_id' => 'The recipient must be assigned to the selected branch.']);
        }

        return DB::transaction(function () use ($branch, $destination, $items, $year, $performedBy, $notes): BookTransfer {
            $transfer = $this->makeTransfer('branch_to_user', $branch, $branch, null, $destination, $year, $performedBy, $notes);
            foreach ($this->normalise($items) as $line) {
                $book = Book::query()->findOrFail($line['book_id']);
                if ($this->inventory->availableForCustody($book, $branch) < $line['quantity']) {
                    throw ValidationException::withMessages(['items' => "The branch does not have enough unassigned copies of {$book->title}."]);
                }
                $to = $this->custody($branch, $destination, $book, true);
                $before = (int) $to->quantity_on_hand;
                $to->increment('quantity_on_hand', $line['quantity']);
                $transfer->items()->create($line);
                $this->recordCustodyMovement($book, $before, $line['quantity'], $transfer, $year, $performedBy, $branch, $destination, 'Assigned from branch stock', $notes);
            }
            $this->audit->log('book_transfer.user_assigned', $transfer, [], ['branch' => $branch->code, 'destination_user_id' => $destination->id, 'items' => $transfer->items()->count()]);

            return $transfer;
        });
    }

    public function reverse(BookTransfer $transfer, User $performedBy, ?string $notes = null): BookTransfer
    {
        return DB::transaction(function () use ($transfer, $performedBy, $notes): BookTransfer {
            $transfer = BookTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status !== 'completed') {
                throw ValidationException::withMessages(['transfer' => 'This transfer has already been reversed.']);
            }

            $items = $transfer->items()->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['transfer' => 'A transfer without books cannot be reversed.']);
            }

            $transfer->loadMissing('sourceBranch', 'destinationBranch', 'sourceUser', 'destinationUser', 'academicYear');
            match ($transfer->transfer_type) {
                'branch_to_branch' => $this->reverseBranchTransfer($transfer, $items->all(), $performedBy, $notes),
                'branch_to_user' => $this->reverseBranchCustodyTransfer($transfer, $items->all(), $performedBy, $notes),
                'user_to_user' => $this->reverseUserTransfer($transfer, $items->all(), $performedBy, $notes),
                default => throw ValidationException::withMessages(['transfer' => 'This transfer type cannot be reversed.']),
            };

            $transfer->update([
                'status' => 'reversed',
                'reversal_notes' => $notes,
                'reversed_by' => $performedBy->id,
                'reversed_at' => now(),
            ]);
            $this->audit->log('book_transfer.reversed', $transfer, ['status' => 'completed'], ['status' => 'reversed', 'items' => $items->count()], $notes);

            return $transfer;
        }, 3);
    }

    /** @param list<\App\Models\BookTransferItem> $items */
    private function reverseBranchTransfer(BookTransfer $transfer, array $items, User $performedBy, ?string $notes): void
    {
        $source = $transfer->sourceBranch;
        $destination = $transfer->destinationBranch;
        if ($source === null || $destination === null) {
            throw ValidationException::withMessages(['transfer' => 'Both branches must still exist to reverse this transfer.']);
        }

        foreach ($items as $item) {
            $book = Book::query()->findOrFail($item->book_id);
            $this->inventory->change($book, -(int) $item->quantity, 'branch_transfer_reversal_out', $transfer, $transfer->academicYear, $performedBy, 'Transfer reversal to '.$source->name, $notes, false, $destination);
            $this->inventory->change($book, (int) $item->quantity, 'branch_transfer_reversal_in', $transfer, $transfer->academicYear, $performedBy, 'Transfer reversal from '.$destination->name, $notes, false, $source);
        }
    }

    /** @param list<\App\Models\BookTransferItem> $items */
    private function reverseBranchCustodyTransfer(BookTransfer $transfer, array $items, User $performedBy, ?string $notes): void
    {
        $branch = $transfer->sourceBranch;
        $holder = $transfer->destinationUser;
        if ($branch === null || $holder === null) {
            throw ValidationException::withMessages(['transfer' => 'The branch and user must still exist to reverse this custody transfer.']);
        }

        foreach ($items as $item) {
            $book = Book::query()->findOrFail($item->book_id);
            $custody = $this->custody($branch, $holder, $book, true);
            if ((int) $custody->quantity_on_hand < (int) $item->quantity) {
                throw ValidationException::withMessages(['transfer' => "{$holder->name} no longer holds enough copies of {$book->title} to reverse this transfer."]);
            }

            $before = (int) $custody->quantity_on_hand;
            $custody->decrement('quantity_on_hand', (int) $item->quantity);
            $this->recordCustodyMovement($book, $before, -(int) $item->quantity, $transfer, $transfer->academicYear, $performedBy, $branch, $holder, 'Transfer reversal to branch stock', $notes);
        }
    }

    /** @param list<\App\Models\BookTransferItem> $items */
    private function reverseUserTransfer(BookTransfer $transfer, array $items, User $performedBy, ?string $notes): void
    {
        $branch = $transfer->sourceBranch;
        $source = $transfer->sourceUser;
        $destination = $transfer->destinationUser;
        if ($branch === null || $source === null || $destination === null) {
            throw ValidationException::withMessages(['transfer' => 'The branch and users must still exist to reverse this custody transfer.']);
        }

        foreach ($items as $item) {
            $book = Book::query()->findOrFail($item->book_id);
            $from = $this->custody($branch, $destination, $book, true);
            if ((int) $from->quantity_on_hand < (int) $item->quantity) {
                throw ValidationException::withMessages(['transfer' => "{$destination->name} no longer holds enough copies of {$book->title} to reverse this transfer."]);
            }
            $to = $this->custody($branch, $source, $book, true);

            $fromBefore = (int) $from->quantity_on_hand;
            $toBefore = (int) $to->quantity_on_hand;
            $from->decrement('quantity_on_hand', (int) $item->quantity);
            $to->increment('quantity_on_hand', (int) $item->quantity);
            $this->recordCustodyMovement($book, $fromBefore, -(int) $item->quantity, $transfer, $transfer->academicYear, $performedBy, $branch, $destination, 'Transfer reversal to '.$source->name, $notes);
            $this->recordCustodyMovement($book, $toBefore, (int) $item->quantity, $transfer, $transfer->academicYear, $performedBy, $branch, $source, 'Transfer reversal from '.$destination->name, $notes);
        }
    }

    private function makeTransfer(string $type, Branch $source, Branch $destination, ?User $sourceUser, ?User $destinationUser, ?AcademicYear $year, User $user, ?string $notes): BookTransfer
    {
        return BookTransfer::create([
            'transfer_number' => $this->nextTransferNumber(),
            'transfer_type' => $type,
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'source_user_id' => $sourceUser?->id,
            'destination_user_id' => $destinationUser?->id,
            'academic_year_id' => $year?->id,
            'transferred_on' => today(),
            'status' => 'completed',
            'notes' => $notes,
            'created_by' => $user->id,
            'completed_by' => $user->id,
        ]);
    }

    private function nextTransferNumber(): string
    {
        do {
            $number = 'TRF-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (BookTransfer::query()->where('transfer_number', $number)->exists());

        return $number;
    }

    private function normalise(array $items): array
    {
        $lines = collect($items)
            ->map(fn (array $item): array => [
                'book_id' => (int) ($item['book_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ])
            ->filter(fn (array $item): bool => $item['quantity'] > 0)
            ->values()
            ->all();
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one book and quantity.']);
        }
        if (count(array_unique(array_column($lines, 'book_id'))) !== count($lines)) {
            throw ValidationException::withMessages(['items' => 'Each book can be included only once.']);
        }

        return $lines;
    }

    private function custody(Branch $branch, User $user, Book $book, bool $lock): UserBookStock
    {
        $query = UserBookStock::query()->where(['branch_id' => $branch->id, 'user_id' => $user->id, 'book_id' => $book->id]);
        if ($lock) {
            $query->lockForUpdate();
        }
        $stock = $query->first();
        if (! $stock) {
            UserBookStock::query()->create(['branch_id' => $branch->id, 'user_id' => $user->id, 'book_id' => $book->id]);
            $stock = $query->firstOrFail();
        }
        return $stock;
    }

    private function recordCustodyMovement(Book $book, int $before, int $quantity, BookTransfer $transfer, ?AcademicYear $year, User $performedBy, Branch $branch, User $holder, string $reason, ?string $notes): void
    {
        StockMovement::withoutGlobalScope('branch')->create([
            'branch_id' => $branch->id,
            'book_id' => $book->id,
            'type' => 'user_custody_transfer',
            'quantity_before' => $before,
            'quantity_change' => $quantity,
            'quantity_after' => $before + $quantity,
            'reference_type' => $transfer::class,
            'reference_id' => $transfer->id,
            'academic_year_id' => $year?->id,
            'user_id' => $performedBy->id,
            'holder_user_id' => $holder->id,
            'reason' => $reason,
            'notes' => $notes,
        ]);
    }
}
