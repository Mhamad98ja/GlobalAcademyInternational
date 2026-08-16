<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDistributionRequest;
use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\ClassBookList;
use App\Models\StudentRegistration;
use App\Services\BookDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DistributionController extends Controller
{
    public function index(): View
    {
        $canViewFinancials = auth()->user()->canViewFinancials();

        return view('distributions.index', [
            'distributions' => BookDistribution::with('student', 'invoice')->latest('distributed_on')->paginate(15),
            'canViewFinancials' => $canViewFinancials,
        ]);
    }

    public function create(Request $request): View
    {
        $canViewFinancials = $request->user()->canViewFinancials();
        $packages = ClassBookList::query()
            ->where('is_active', true)
            ->with('items.book', 'section')
            ->orderBy('name')
            ->get();
        $books = Book::active()->stockedInBranch()->withBranchStock()->orderBy('title')->get();

        return view('distributions.form', [
            'registrations' => StudentRegistration::with('student', 'academicYear', 'grade', 'section')
                ->where('status', 'active')
                ->latest()
                ->take(100)
                ->get(),
            'packages' => $packages,
            'packageData' => $packages->map(fn (ClassBookList $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'academic_year_id' => $package->academic_year_id,
                'grade_id' => $package->grade_id,
                'section_id' => $package->section_id,
                'section_label' => $package->section?->name ?? 'All sections',
                'items' => $package->items->map(fn ($item) => [
                    'book_id' => $item->book_id,
                    'quantity' => $item->quantity,
                ])->values(),
            ])->values(),
            'bookData' => $books->map(function (Book $book) use ($canViewFinancials): array {
                $data = [
                    'id' => $book->id,
                    'barcode' => $book->barcode,
                    'title' => $book->title,
                    'available_quantity' => $book->available_quantity,
                ];
                if ($canViewFinancials) {
                    $data['selling_price'] = (string) $book->selling_price;
                }

                return $data;
            })->values(),
            'selectedRegistration' => $request->integer('registration'),
            'canViewFinancials' => $canViewFinancials,
        ]);
    }

    public function store(StoreDistributionRequest $request, BookDistributionService $service): RedirectResponse
    {
        $data = $request->validated();
        $registration = StudentRegistration::findOrFail($data['student_registration_id']);
        $package = $this->matchingPackage(
            $registration,
            isset($data['class_book_list_id']) ? (int) $data['class_book_list_id'] : null,
        );

        $distribution = $service->distribute(
            $registration,
            $this->itemsFromBarcodes($data['items']),
            $request->user()->canViewFinancials() ? ($data['payment'] ?? []) : [],
            $request->user(),
            $data['notes'] ?? null,
            $package,
        );

        return redirect()->route(
            $request->user()->canViewFinancials() ? 'invoices.show' : 'distributions.show',
            $request->user()->canViewFinancials() ? $distribution->invoice : $distribution,
        )->with('success', 'Books distributed successfully.');
    }

    public function show(BookDistribution $distribution, Request $request): View
    {
        $distribution->load('student', 'registration.grade', 'registration.section', 'items.book', 'invoice');

        return view('distributions.show', ['distribution' => $distribution, 'canViewFinancials' => $request->user()->canViewFinancials()]);
    }

    private function matchingPackage(StudentRegistration $registration, ?int $packageId): ?ClassBookList
    {
        if (! $packageId) {
            return null;
        }

        $package = ClassBookList::with('items')->findOrFail($packageId);
        $isCompatible = $package->is_active
            && (int) $package->academic_year_id === (int) $registration->academic_year_id
            && (int) $package->grade_id === (int) $registration->grade_id
            && ($package->section_id === null || (int) $package->section_id === (int) $registration->section_id);

        if (! $isCompatible) {
            throw ValidationException::withMessages([
                'class_book_list_id' => 'The selected book package does not match this student registration.',
            ]);
        }

        return $package;
    }

    /** @param list<array{barcode: string, quantity: int|string}> $items @return list<array{book_id: int, quantity: int}> */
    private function itemsFromBarcodes(array $items): array
    {
        $resolved = collect($items)->map(function (array $item): array {
            $search = trim($item['barcode']);
            $searchTerm = mb_strtolower($search);
            $book = Book::active()->stockedInBranch()->whereRaw('LOWER(barcode) = ?', [$searchTerm])->first();
            $book ??= Book::active()->stockedInBranch()->whereRaw('LOWER(title) = ?', [$searchTerm])->first();
            if ($book === null) {
                throw ValidationException::withMessages(['items' => "No active book matches barcode or title {$search}."]);
            }

            return ['book_id' => $book->id, 'quantity' => (int) $item['quantity']];
        })->values();

        if ($resolved->pluck('book_id')->unique()->count() !== $resolved->count()) {
            throw ValidationException::withMessages(['items' => 'Select each book only once. Increase its quantity instead.']);
        }

        return $resolved->all();
    }
}
