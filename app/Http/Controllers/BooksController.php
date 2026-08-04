<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BooksController extends Controller
{
    public function index(Request $request): View
    {
        $books = Book::query()
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($books) => $books
                ->where('title', 'like', '%'.$request->search.'%')
                ->orWhere('barcode', 'like', '%'.$request->search.'%')
                ->orWhere('isbn', 'like', '%'.$request->search.'%')
                ->orWhere('sku', 'like', '%'.$request->search.'%')))
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return view('books.index', compact('books'));
    }

    public function create(): View
    {
        return view('books.form', ['book' => new Book()]);
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['purchase_price'] = '0.00';
        $data['is_active'] = $request->boolean('is_active', true);
        $book = Book::create($data);

        return redirect()->route('books.show', $book)->with('success', 'Book added to the catalogue. Add a supplier purchase to set its purchase price.');
    }

    public function show(Book $book): View
    {
        $book->load(['movements' => fn ($query) => $query->with('user')->latest()->take(20)]);

        return view('books.show', compact('book'));
    }

    public function edit(Book $book): View
    {
        return view('books.form', compact('book'));
    }

    public function update(StoreBookRequest $request, Book $book): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $book->update($data);

        return redirect()->route('books.show', $book)->with('success', 'Book updated.');
    }

    public function destroy(Book $book, AuditService $audit): RedirectResponse
    {
        $book->delete();
        $audit->log('book.deleted', $book);

        return redirect()->route('books.index')->with('success', 'Book moved to recycle bin.');
    }
}
