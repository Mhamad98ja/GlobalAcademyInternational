<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Section;
use App\Models\StudentRegistration;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        [$report, $rows, $filters] = $this->reportData($request);

        return view('reports.index', [
            'report' => $report,
            'rows' => $rows,
            'filters' => $filters,
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'grades' => Grade::with('sections')->orderBy('sort_order')->get(),
        ]);
    }

    public function print(Request $request): View
    {
        [$report, $rows, $filters] = $this->reportData($request);
        $school = SystemSetting::query()->whereIn('key', ['school_name', 'school_address', 'school_phone'])->pluck('value', 'key');
        $filterSummary = $this->filterSummary($filters);

        return view('reports.print', compact('report', 'rows', 'filters', 'school', 'filterSummary'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $report = $this->normaliseReport($request->string('report')->toString());
        $filters = $this->filters($request);
        $query = $this->reportQuery($report, $filters);

        return response()->streamDownload(function () use ($report, $query): void {
            $output = fopen('php://output', 'w');

            if ($report === 'stock') {
                fputcsv($output, ['SKU', 'Title', 'On hand', 'Reserved', 'Available']);
                foreach ($query->cursor() as $book) {
                    fputcsv($output, [$book->sku, $book->title, $book->stock_on_hand, $book->reserved_quantity, $book->available_quantity]);
                }
            } elseif ($report === 'invoices') {
                fputcsv($output, ['Invoice', 'Student', 'Date', 'Total', 'Paid', 'Balance', 'Payment status']);
                foreach ($query->cursor() as $invoice) {
                    fputcsv($output, [
                        $invoice->invoice_number,
                        $invoice->student?->full_name,
                        $invoice->invoice_date?->format('Y-m-d'),
                        $invoice->total,
                        $invoice->amount_paid,
                        $invoice->balance_due,
                        $invoice->payment_status,
                    ]);
                }
            } elseif ($report === 'payments') {
                fputcsv($output, ['Payment', 'Invoice', 'Student', 'Date', 'Amount', 'Method', 'Status']);
                foreach ($query->cursor() as $payment) {
                    fputcsv($output, [
                        $payment->payment_number,
                        $payment->invoice?->invoice_number,
                        $payment->student?->full_name,
                        $payment->payment_date?->format('Y-m-d'),
                        $payment->amount,
                        $payment->method,
                        $payment->status,
                    ]);
                }
            } else {
                fputcsv($output, ['Student', 'Year', 'Grade', 'Section', 'Status']);
                foreach ($query->cursor() as $registration) {
                    fputcsv($output, [
                        $registration->student?->full_name,
                        $registration->academicYear?->name,
                        $registration->grade?->name,
                        $registration->section?->name,
                        $registration->status,
                    ]);
                }
            }

            fclose($output);
        }, $report.'-report.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: string, 1: \Illuminate\Support\Collection<int, mixed>, 2: array<string, mixed>} */
    private function reportData(Request $request): array
    {
        $report = $this->normaliseReport($request->string('report')->toString());
        $filters = $this->filters($request);

        return [$report, $this->reportQuery($report, $filters)->get(), $filters];
    }

    /** @param array<string, mixed> $filters */
    private function reportQuery(string $report, array $filters): Builder
    {
        return match ($report) {
            'stock' => Book::query()
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $books) => $books
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%')))
                ->when(($filters['stock_level'] ?? null) === 'in_stock', fn (Builder $query) => $query->where('stock_on_hand', '>', 0))
                ->when(($filters['stock_level'] ?? null) === 'low_stock', fn (Builder $query) => $query->whereColumn('stock_on_hand', '<=', 'minimum_stock'))
                ->when(($filters['stock_level'] ?? null) === 'out_of_stock', fn (Builder $query) => $query->where('stock_on_hand', '<=', 0))
                ->orderBy('stock_on_hand')
                ->orderBy('title'),
            'invoices' => Invoice::query()
                ->with('student')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $invoices) => $invoices
                    ->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhereHas('student', fn (Builder $student) => $student
                        ->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('student_number', 'like', '%'.$search.'%'))))
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
                ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
                ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
                ->when($filters['invoice_payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '<=', $date))
                ->latest('invoice_date'),
            'payments' => Payment::query()
                ->with('student', 'invoice')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $payments) => $payments
                    ->where('payment_number', 'like', '%'.$search.'%')
                    ->orWhere('reference_number', 'like', '%'.$search.'%')
                    ->orWhereHas('student', fn (Builder $student) => $student
                        ->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('student_number', 'like', '%'.$search.'%'))
                    ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('invoice_number', 'like', '%'.$search.'%'))))
                ->when($filters['payment_method'] ?? null, fn (Builder $query, string $method) => $query->where('method', $method))
                ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('payment_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('payment_date', '<=', $date))
                ->when(
                    ($filters['academic_year_id'] ?? null) || ($filters['grade_id'] ?? null) || ($filters['section_id'] ?? null),
                    fn (Builder $query) => $query->whereHas('invoice', fn (Builder $invoice) => $invoice
                        ->when($filters['academic_year_id'] ?? null, fn (Builder $invoices, int $id) => $invoices->where('academic_year_id', $id))
                        ->when($filters['grade_id'] ?? null, fn (Builder $invoices, int $id) => $invoices->where('grade_id', $id))
                        ->when($filters['section_id'] ?? null, fn (Builder $invoices, int $id) => $invoices->where('section_id', $id))),
                )
                ->latest('payment_date'),
            default => StudentRegistration::query()
                ->with('student', 'academicYear', 'grade', 'section')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('student', fn (Builder $student) => $student
                    ->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('student_number', 'like', '%'.$search.'%')
                    ->orWhere('registration_number', 'like', '%'.$search.'%')))
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
                ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
                ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
                ->when($filters['registration_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('registered_on', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('registered_on', '<=', $date))
                ->latest('registered_on'),
        };
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'registration_status' => ['nullable', 'in:active,inactive,withdrawn,completed'],
            'invoice_payment_status' => ['nullable', 'in:unpaid,partially_paid,paid,deferred'],
            'payment_status' => ['nullable', 'in:valid,reversed'],
            'payment_method' => ['nullable', 'in:cash,card,bank_transfer,check,other'],
            'stock_level' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function normaliseReport(string $report): string
    {
        return in_array($report, ['students', 'stock', 'invoices', 'payments'], true) ? $report : 'students';
    }

    /** @param array<string, mixed> $filters
     *  @return list<string>
     */
    private function filterSummary(array $filters): array
    {
        $academicYear = isset($filters['academic_year_id']) ? AcademicYear::find($filters['academic_year_id']) : null;
        $grade = isset($filters['grade_id']) ? Grade::find($filters['grade_id']) : null;
        $section = isset($filters['section_id']) ? Section::find($filters['section_id']) : null;

        return array_values(array_filter([
            isset($filters['search']) ? 'Search: '.$filters['search'] : null,
            $academicYear ? 'Academic year: '.$academicYear->name : null,
            $grade ? 'Grade: '.$grade->name : null,
            $section ? 'Section: '.$section->name : null,
            isset($filters['registration_status']) ? 'Registration: '.str($filters['registration_status'])->headline() : null,
            isset($filters['invoice_payment_status']) ? 'Invoice payment: '.str($filters['invoice_payment_status'])->headline() : null,
            isset($filters['payment_method']) ? 'Method: '.str($filters['payment_method'])->headline() : null,
            isset($filters['payment_status']) ? 'Payment: '.str($filters['payment_status'])->headline() : null,
            isset($filters['stock_level']) ? 'Stock: '.str($filters['stock_level'])->headline() : null,
            isset($filters['date_from']) ? 'From: '.$filters['date_from'] : null,
            isset($filters['date_to']) ? 'To: '.$filters['date_to'] : null,
        ]));
    }
}
