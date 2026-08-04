<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
final class InventoryService
{
    public function change(Book|int $book, int $quantity, string $type, ?Model $reference, ?AcademicYear $year, ?User $user, string $reason, ?string $notes = null, bool $allowNegative = false): Book
    {
        $bookId = $book instanceof Book ? $book->id : $book;
        $locked = Book::query()->lockForUpdate()->findOrFail($bookId);
        $before = (int) $locked->stock_on_hand;
        $after = $before + $quantity;
        $available = $before - (int) $locked->reserved_quantity;
        if ($quantity < 0 && -$quantity > $available && !$allowNegative) {
            throw ValidationException::withMessages(['items' => "Insufficient stock for {$locked->title}. Available: {$available}."]);
        }
        $locked->stock_on_hand = $after;
        $locked->save();
        StockMovement::create(['book_id'=>$locked->id,'type'=>$type,'quantity_before'=>$before,'quantity_change'=>$quantity,'quantity_after'=>$after,'reference_type'=>$reference ? $reference::class : null,'reference_id'=>$reference?->getKey(),'academic_year_id'=>$year?->id,'user_id'=>$user?->id,'reason'=>$reason,'notes'=>$notes]);
        return $locked;
    }
}
