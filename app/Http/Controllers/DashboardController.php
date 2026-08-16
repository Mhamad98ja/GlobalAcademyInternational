<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\DeferredPaymentApproval;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StudentRegistration;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $canViewFinancials = $request->user()->canViewFinancials();
        $activeAcademicYear = AcademicYear::query()
            ->where('status', 'open')
            ->orderByDesc('starts_on')
            ->first();
        $academicYearId = $activeAcademicYear?->id;
        $books = Book::query()
            ->active()
            ->withBranchStock()
            ->knownInBranch()
            ->get();

        return view('dashboard', [
            'activeAcademicYear' => $activeAcademicYear,
            'activeStudents' => $academicYearId
                ? StudentRegistration::query()->where('status', 'active')->where('academic_year_id', $academicYearId)->count()
                : 0,
            'stock' => $books->sum('branch_quantity'),
            'lowStock' => $books->filter(fn (Book $book) => $book->branch_quantity <= $book->minimum_stock)->sortBy('branch_quantity')->take(6),
            'canViewFinancials' => $canViewFinancials,
            'collected' => $canViewFinancials && $academicYearId
                ? Payment::query()->where('status', 'valid')->whereDate('payment_date', today())->whereHas('invoice', fn ($query) => $query->where('academic_year_id', $academicYearId))->sum('amount')
                : null,
            'outstanding' => $canViewFinancials && $academicYearId
                ? Invoice::query()->where('academic_year_id', $academicYearId)->sum('balance_due')
                : null,
            'todayDistributions' => $academicYearId
                ? BookDistribution::query()->where('academic_year_id', $academicYearId)->whereDate('distributed_on', today())->count()
                : 0,
            'overdueDeferred' => $canViewFinancials && $academicYearId
                ? DeferredPaymentApproval::query()->whereIn('status', ['approved', 'active', 'partially_settled'])->whereDate('expected_payment_date', '<', today())->whereHas('invoice', fn ($query) => $query->where('academic_year_id', $academicYearId))->count()
                : null,
            'recentActivity' => StockMovement::query()->with('book', 'user')->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId), fn ($query) => $query->whereRaw('1 = 0'))->latest()->take(6)->get(),
        ]);
    }
}
