<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Section;
use App\Models\Student;
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
        $school = SystemSetting::query()
            ->whereIn('key', ['school_name', 'school_address', 'school_phone'])
            ->pluck('value', 'key');

        return view('reports.print', [
            'report' => $report,
            'rows' => $rows,
            'filters' => $filters,
            'school' => $school,
            'filterSummary' => $this->filterSummary($filters),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $report = $this->normaliseReport($request->string('report')->toString());
        $filters = $this->filters($request);
        $query = $this->reportQuery($report, $filters);

        return response()->streamDownload(function () use ($report, $query, $filters): void {
            $output = fopen('php://output', 'w');

            if ($report === 'stock') {
                fputcsv($output, ['SKU', 'Title', 'On hand', 'Reserved', 'Available']);
                foreach ($query->cursor() as $book) {
                    $level = $filters['stock_level'] ?? null;
                    if (($level === 'in_stock' && $book->branch_quantity < 1)
                        || ($level === 'low_stock' && $book->branch_quantity > $book->minimum_stock)
                        || ($level === 'out_of_stock' && $book->branch_quantity > 0)) {
                        continue;
                    }
                    fputcsv($output, [$book->sku, $book->title, $book->branch_quantity, $book->branchStocks->first()?->reserved_quantity ?? 0, $book->available_quantity]);
                }
            } elseif ($report === 'invoices') {
                fputcsv($output, ['Invoice', 'Student', 'Date', 'Total', 'Paid', 'Balance', 'Payment status', 'Issued by']);
                foreach ($query->lazy() as $invoice) {
                    fputcsv($output, [
                        $invoice->invoice_number,
                        $invoice->student?->full_name,
                        $invoice->invoice_date?->format('Y-m-d'),
                        $invoice->total,
                        $invoice->amount_paid,
                        $invoice->balance_due,
                        $invoice->payment_status,
                        $invoice->createdBy?->name,
                    ]);
                }
            } elseif ($report === 'distributions') {
                fputcsv($output, ['Distribution', 'Invoice', 'Student', 'Date', 'Books issued', 'Distributed by']);
                foreach ($query->lazy() as $distribution) {
                    fputcsv($output, [
                        'Distribution #'.$distribution->id,
                        $distribution->invoice?->invoice_number,
                        $distribution->student?->full_name,
                        $distribution->distributed_on?->format('Y-m-d'),
                        $distribution->items->sum('quantity'),
                        $distribution->distributedBy?->name,
                    ]);
                }
            } elseif ($report === 'payments') {
                fputcsv($output, ['Payment', 'Invoice', 'Student', 'Date', 'Amount', 'Method', 'Status']);
                foreach ($query->lazy() as $payment) {
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
            } elseif ($report === 'student_information') {
                fputcsv($output, ['Student number', 'Registration number', 'Student', 'Gender', 'Date of birth', 'Phone', 'Email', 'Enrollment date', 'Latest class']);
                foreach ($query->lazy() as $student) {
                    $registration = $student->registrations->first();
                    fputcsv($output, [
                        $student->student_number,
                        $student->registration_number,
                        $student->full_name,
                        $student->gender ? (string) str($student->gender)->headline() : '',
                        $student->date_of_birth?->format('Y-m-d'),
                        $student->phone,
                        $student->email,
                        $student->enrollment_date?->format('Y-m-d'),
                        $registration ? trim(implode(' ', array_filter([$registration->academicYear?->name, $registration->grade?->name, $registration->section?->name]))) : '',
                    ]);
                }
            } else {
                fputcsv($output, $report === 'class_sections'
                    ? ['Academic year', 'Grade', 'Section', 'Student number', 'Registration number', 'Student', 'Gender', 'Status', 'Left school on']
                    : ['Student number', 'Registration number', 'Student', 'Academic year', 'Grade', 'Section', 'Registered on', 'Status', 'Left school on']);

                foreach ($query->lazy() as $registration) {
                    $base = [
                        $registration->student?->student_number,
                        $registration->student?->registration_number,
                        $registration->student?->full_name,
                        $registration->academicYear?->name,
                        $registration->grade?->name,
                        $registration->section?->name,
                        $registration->registered_on?->format('Y-m-d'),
                        $registration->status,
                        $registration->withdrawn_on?->format('Y-m-d'),
                    ];

                    fputcsv($output, $report === 'class_sections'
                        ? [$base[3], $base[4], $base[5], $base[0], $base[1], $base[2], $registration->student?->gender ? (string) str($registration->student->gender)->headline() : '', $base[7], $base[8]]
                        : $base);
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
            'stock' => Book::query()->withBranchStock()
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $books) => $books
                    ->whereInsensitiveLike('title', $search)
                    ->orWhereInsensitiveLike('sku', $search)
                    ->orWhereInsensitiveLike('barcode', $search)))
                ->orderBy('title'),
            'invoices' => Invoice::query()
                ->with('student', 'createdBy')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $invoices) => $invoices
                    ->whereInsensitiveLike('invoice_number', $search)
                    ->orWhereHas('student', fn (Builder $student) => $student
                        ->whereInsensitiveLike('first_name', $search)
                        ->orWhereInsensitiveLike('last_name', $search)
                        ->orWhereInsensitiveLike('student_number', $search))))
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
                ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
                ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
                ->when($filters['invoice_payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('invoice_date', '<=', $date))
                ->latest('invoice_date'),
            'distributions' => BookDistribution::query()
                ->with('student', 'invoice', 'distributedBy', 'items')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $distributions) => $distributions
                    ->whereHas('invoice', fn (Builder $invoice) => $invoice->whereInsensitiveLike('invoice_number', $search))
                    ->orWhereHas('student', fn (Builder $student) => $student
                        ->whereInsensitiveLike('first_name', $search)
                        ->orWhereInsensitiveLike('last_name', $search)
                        ->orWhereInsensitiveLike('student_number', $search))))
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
                ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
                ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('distributed_on', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('distributed_on', '<=', $date))
                ->latest('distributed_on'),
            'payments' => Payment::query()
                ->with('student', 'invoice')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $payments) => $payments
                    ->whereInsensitiveLike('payment_number', $search)
                    ->orWhereInsensitiveLike('reference_number', $search)
                    ->orWhereHas('student', fn (Builder $student) => $student
                        ->whereInsensitiveLike('first_name', $search)
                        ->orWhereInsensitiveLike('last_name', $search)
                        ->orWhereInsensitiveLike('student_number', $search))
                    ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->whereInsensitiveLike('invoice_number', $search))))
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
            'student_information' => Student::query()
                ->with(['registrations' => fn ($registrations) => $registrations
                    ->with('academicYear', 'grade', 'section')
                    ->latest('registered_on')])
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $students) => $students
                    ->whereInsensitiveLike('student_number', $search)
                    ->orWhereInsensitiveLike('registration_number', $search)
                    ->orWhereInsensitiveLike('first_name', $search)
                    ->orWhereInsensitiveLike('last_name', $search)
                    ->orWhereInsensitiveLike('phone', $search)
                    ->orWhereInsensitiveLike('email', $search)))
                ->when($filters['gender'] ?? null, fn (Builder $query, string $gender) => $query->where('gender', $gender))
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'class_sections' => $this->registrationReportQuery($filters, true),
            default => $this->registrationReportQuery($filters),
        };
    }

    /** @param array<string, mixed> $filters */
    private function registrationReportQuery(array $filters, bool $classList = false): Builder
    {
        return StudentRegistration::query()
            ->with('student', 'academicYear', 'grade', 'section')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('student', fn (Builder $student) => $student
                ->whereInsensitiveLike('first_name', $search)
                ->orWhereInsensitiveLike('last_name', $search)
                ->orWhereInsensitiveLike('student_number', $search)
                ->orWhereInsensitiveLike('registration_number', $search)))
            ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
            ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
            ->when($filters['registration_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(! $classList && ($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('registered_on', '>=', $filters['date_from']))
            ->when(! $classList && ($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('registered_on', '<=', $filters['date_to']))
            ->when($classList, fn (Builder $query) => $query
                ->orderByDesc('academic_year_id')
                ->orderBy('grade_id')
                ->orderBy('section_id'))
            ->when(! $classList, fn (Builder $query) => $query->latest('registered_on'));
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
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
        return in_array($report, ['students', 'student_information', 'class_sections', 'stock', 'invoices', 'distributions', 'payments'], true)
            ? $report
            : 'students';
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
            isset($filters['gender']) ? 'Gender: '.str($filters['gender'])->headline() : null,
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
