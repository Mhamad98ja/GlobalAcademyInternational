<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDistributionRequest;
use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\ClassBookList;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\StudentRegistration;
use App\Models\SystemSetting;
use App\Services\BookDistributionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DistributionController extends Controller
{
    public function index(Request $request): View
    {
        $canViewFinancials = $request->user()->canViewFinancials();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return view('distributions.index', [
            'distributions' => BookDistribution::query()
                ->with('student', 'invoice')
                ->when($filters['search'] ?? null, function (Builder $query, string $search) use ($canViewFinancials): void {
                    $query->where(function (Builder $distributions) use ($search, $canViewFinancials): void {
                        $distributions->whereInsensitiveLike('public_id', $search)
                            ->orWhereHas('student', fn (Builder $student) => $student
                                ->whereInsensitiveLike('first_name', $search)
                                ->orWhereInsensitiveLike('last_name', $search)
                                ->orWhereInsensitiveLike('student_number', $search));

                        if ($canViewFinancials) {
                            $distributions->orWhereHas('invoice', fn (Builder $invoice) => $invoice->whereInsensitiveLike('invoice_number', $search));
                        }
                    });
                })
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
                ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
                ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('distributed_on', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('distributed_on', '<=', $date))
                ->latest('distributed_on')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'canViewFinancials' => $canViewFinancials,
            'filters' => $filters,
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'grades' => Grade::with('sections')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $canViewFinancials = $request->user()->canViewFinancials();
        $canRequestDeferred = $request->user()->hasPermission('deferred.create');
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
                'items' => $package->items->map(function ($item) use ($canViewFinancials): array {
                    $data = [
                        'book_id' => $item->book_id,
                        'quantity' => $item->quantity,
                    ];
                    if ($canViewFinancials) {
                        $data['price_type'] = $item->price_type;
                        $data['unit_price'] = (string) $item->unit_price;
                    }

                    return $data;
                })->values(),
            ])->values(),
            'bookData' => $books->map(function (Book $book) use ($canViewFinancials): array {
                $data = [
                    'id' => $book->id,
                    'barcode' => $book->barcode,
                    'isbn' => $book->isbn,
                    'title' => $book->title,
                    'available_quantity' => $book->available_quantity,
                ];
                if ($canViewFinancials) {
                    $data['selling_price'] = (string) $book->selling_price;
                    $data['tax_rate'] = (string) $book->tax_rate;
                }

                return $data;
            })->values(),
            'selectedRegistration' => $request->integer('registration'),
            'canViewFinancials' => $canViewFinancials,
            'canRequestDeferred' => $canRequestDeferred,
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

        $payment = $request->user()->canViewFinancials() ? ($data['payment'] ?? []) : [];
        if ($request->user()->hasPermission('deferred.create') && ! empty($data['payment']['deferred'])) {
            $payment['deferred'] = true;
        }

        $distribution = $service->distribute(
            $registration,
            $this->itemsFromBarcodes($data['items']),
            $payment,
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

    public function print(BookDistribution $distribution): View
    {
        $distribution->load('invoice.student', 'invoice.registration.academicYear', 'invoice.registration.grade', 'invoice.registration.section', 'invoice.grade', 'invoice.section', 'invoice.items.book', 'invoice.responsiblePerson', 'invoice.createdBy');
        $invoice = $distribution->invoice;
        abort_unless($invoice, 404);

        $school = SystemSetting::query()
            ->whereIn('key', ['school_name', 'school_address', 'school_phone'])
            ->pluck('value', 'key');

        return view('invoices.print', [
            'invoice' => $invoice,
            'school' => $school,
            'copy' => 'student',
            'backUrl' => route('distributions.show', $distribution),
            'backLabel' => 'Back to distribution',
        ]);
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
            $book ??= Book::active()->stockedInBranch()->whereRaw('LOWER(isbn) = ?', [$searchTerm])->first();
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
