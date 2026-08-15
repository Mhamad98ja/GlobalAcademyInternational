<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use App\Models\ClassBookList;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BooksController extends Controller
{
    public function index(Request $request): View
    {
        $canManagePackages = $request->user()->hasPermission('class-lists.manage');
        $canManageBooks = $request->user()->hasPermission('books.manage');
        $canViewFinancials = $request->user()->canViewFinancials();
        $tab = $canManagePackages && $request->string('tab')->toString() === 'packages' ? 'packages' : 'books';
        $search = trim($request->string('search')->toString());
        $books = null;
        $lists = null;

        if ($tab === 'packages') {
            $lists = ClassBookList::query()
                ->with('academicYear', 'grade', 'section', 'items.book')
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->whereInsensitiveLike('name', $search)
                        ->orWhereHas('academicYear', fn ($year) => $year->whereInsensitiveLike('name', $search))
                        ->orWhereHas('grade', fn ($grade) => $grade->whereInsensitiveLike('name', $search))
                        ->orWhereHas('section', fn ($section) => $section->whereInsensitiveLike('name', $search));
                }))
                ->latest()
                ->paginate(15)
                ->withQueryString();
        } else {
            $books = Book::query()->withBranchStock()->with('gradeUsages')
                ->when($search !== '', fn ($query) => $query->where(fn ($books) => $books
                    ->whereInsensitiveLike('title', $search)
                    ->orWhereInsensitiveLike('barcode', $search)
                    ->orWhereInsensitiveLike('isbn', $search)
                    ->orWhereInsensitiveLike('sku', $search)))
                ->orderBy('title')
                ->paginate(15)
                ->withQueryString();
        }

        return view('books.index', compact('books', 'lists', 'tab', 'search', 'canManageBooks', 'canManagePackages', 'canViewFinancials'));
    }

    public function create(): View
    {
        return view('books.form', ['book' => new Book()]);
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $stockQuantity = (int) $data['stock_quantity'];
        unset($data['stock_quantity']);
        $data['purchase_price'] = '0.00';
        $data['is_active'] = $request->boolean('is_active', true);
        $book = DB::transaction(function () use ($data, $stockQuantity, $request): Book {
            $book = Book::create($data);
            $this->setStockQuantity($book, $stockQuantity, $request->user(), app(InventoryService::class), app(AuditService::class));

            return $book;
        });

        return redirect()->route('books.show', $book)->with('success', 'Book added to the catalogue and its current-branch stock was recorded.');
    }

    public function show(Book $book, Request $request): View
    {
        $book->load(['branchStocks' => fn ($stocks) => $stocks->where('branch_id', app(\App\Services\BranchContext::class)->id()), 'movements' => fn ($query) => $query->with('user')->latest()->take(20), 'gradeUsages.grade']);

        return view('books.show', ['book' => $book, 'canViewFinancials' => $request->user()->canViewFinancials()]);
    }

    public function edit(Book $book): View
    {
        return view('books.form', compact('book'));
    }

    public function update(StoreBookRequest $request, Book $book): RedirectResponse
    {
        $data = $request->validated();
        $stockQuantity = (int) $data['stock_quantity'];
        unset($data['stock_quantity']);
        $data['is_active'] = $request->boolean('is_active');
        $book = DB::transaction(function () use ($book, $data, $stockQuantity, $request): Book {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);
            $book->update($data);
            $this->setStockQuantity($book, $stockQuantity, $request->user(), app(InventoryService::class), app(AuditService::class));

            return $book;
        });

        return redirect()->route('books.show', $book)->with('success', 'Book updated.');
    }

    public function destroy(Book $book, AuditService $audit): RedirectResponse
    {
        $book->delete();
        $audit->log('book.deleted', $book);

        return redirect()->route('books.index')->with('success', 'Book moved to recycle bin.');
    }

    private function setStockQuantity(Book $book, int $targetQuantity, User $user, InventoryService $inventory, AuditService $audit): void
    {
        $currentQuantity = (int) $inventory->balance($book)->quantity_on_hand;
        $quantityChange = $targetQuantity - $currentQuantity;

        if ($quantityChange === 0) {
            return;
        }

        $adjustment = StockAdjustment::create([
            'book_id' => $book->id,
            'quantity_change' => $quantityChange,
            'reason' => 'Stock quantity set from book editor',
            'approved_by' => $user->id,
            'created_by' => $user->id,
        ]);
        $inventory->change(
            $book,
            $quantityChange,
            'manual_adjustment',
            $adjustment,
            null,
            $user,
            'Stock quantity set from book editor',
            null,
            $user->hasPermission('stock.allow-negative'),
        );
        $audit->log('stock.adjusted', $adjustment, [], ['quantity_change' => $quantityChange], 'Stock quantity set from book editor');
    }
}
