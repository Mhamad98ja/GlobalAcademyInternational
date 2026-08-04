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
        return view('distributions.index', [
            'distributions' => BookDistribution::with('student', 'invoice')->latest('distributed_on')->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        $packages = ClassBookList::query()
            ->where('is_active', true)
            ->with('items.book', 'section')
            ->orderBy('name')
            ->get();

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
            'books' => Book::active()->orderBy('title')->get(),
            'selectedRegistration' => $request->integer('registration'),
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
            $data['items'],
            $data['payment'] ?? [],
            $request->user(),
            $data['notes'] ?? null,
            $package,
        );

        return redirect()->route('invoices.show', $distribution->invoice)->with('success', 'Books distributed and invoice issued.');
    }

    public function show(BookDistribution $distribution): View
    {
        $distribution->load('student', 'registration.grade', 'registration.section', 'items.book', 'invoice');

        return view('distributions.show', compact('distribution'));
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
}
