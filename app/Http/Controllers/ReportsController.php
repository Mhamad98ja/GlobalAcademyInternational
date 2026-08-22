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
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportsController extends Controller
{
    private const STUDENT_INFORMATION_FIELDS = [
        'student_number' => 'Student no.',
        'registration_number' => 'Registration no.',
        'full_name' => 'Student',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of birth',
        'phone' => 'Phone',
        'email' => 'Email',
        'enrollment_date' => 'Enrollment',
        'latest_class' => 'Latest class',
    ];

    public function index(Request $request): View
    {
        [$report, $rows, $filters] = $this->reportData($request);
        $selectionKey = $this->selectionKey($report);

        return view('reports.index', [
            'report' => $report,
            'rows' => $rows,
            'filters' => $filters,
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'grades' => Grade::with('sections')->orderBy('sort_order')->get(),
            'canViewFinancials' => $request->user()->canViewFinancials(),
            'studentInformationFields' => $this->studentInformationFields($filters),
            'studentInformationFieldOptions' => self::STUDENT_INFORMATION_FIELDS,
            'selectableReportRows' => $selectionKey !== null,
            'selectionKey' => $selectionKey,
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
            'canViewFinancials' => $request->user()->canViewFinancials(),
            'studentInformationFields' => $this->studentInformationFields($filters),
            'selectableReportRows' => false,
            'selectionKey' => null,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $canViewFinancials = $request->user()->canViewFinancials();
        $report = $this->normaliseReport($request->string('report')->toString(), $canViewFinancials);
        $filters = $this->filters($request);
        $query = $this->reportQuery($report, $filters, $canViewFinancials);

        return response()->streamDownload(function () use ($report, $query, $filters, $canViewFinancials): void {
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
                fputcsv($output, $canViewFinancials
                    ? ['Distribution', 'Invoice', 'Student', 'Date', 'Books issued', 'Distributed by']
                    : ['Distribution', 'Student', 'Date', 'Books issued', 'Distributed by']);
                foreach ($query->lazy() as $distribution) {
                    $row = [
                        'Distribution #'.$distribution->id,
                        $distribution->student?->full_name,
                        $distribution->distributed_on?->format('Y-m-d'),
                        $distribution->items->sum('quantity'),
                        $distribution->distributedBy?->name,
                    ];
                    if ($canViewFinancials) {
                        array_splice($row, 1, 0, [$distribution->invoice?->invoice_number]);
                    }
                    fputcsv($output, $row);
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
                $studentInformationFields = $this->studentInformationFields($filters);
                fputcsv($output, array_values($studentInformationFields));
                foreach ($query->lazy() as $student) {
                    $registration = $student->registrations->first();
                    $row = $this->studentInformationRow($student, $registration);
                    fputcsv($output, array_values(array_intersect_key($row, $studentInformationFields)));
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
        $canViewFinancials = $request->user()->canViewFinancials();
        $report = $this->normaliseReport($request->string('report')->toString(), $canViewFinancials);
        $filters = $this->filters($request);

        return [$report, $this->reportQuery($report, $filters, $canViewFinancials)->get(), $filters];
    }

    /** @param array<string, mixed> $filters */
    private function reportQuery(string $report, array $filters, bool $canViewFinancials): Builder
    {
        return match ($report) {
            'stock' => Book::query()->withBranchStock()
                ->knownInBranch()
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $books) => $books
                    ->whereInsensitiveLike('title', $search)
                    ->orWhereInsensitiveLike('sku', $search)
                    ->orWhereInsensitiveLike('barcode', $search)))
                ->when($filters['book_ids'] ?? [], fn (Builder $query, array $bookIds) => $query->whereKey($bookIds))
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
                ->when($filters['invoice_ids'] ?? [], fn (Builder $query, array $invoiceIds) => $query->whereKey($invoiceIds))
                ->latest('invoice_date'),
            'distributions' => BookDistribution::query()
                ->with('student', 'distributedBy', 'items')
                ->when($canViewFinancials, fn (Builder $query) => $query->with('invoice'))
                ->when($filters['search'] ?? null, function (Builder $query, string $search) use ($canViewFinancials): void {
                    $query->where(function (Builder $distributions) use ($search, $canViewFinancials): void {
                        if ($canViewFinancials) {
                            $distributions->whereHas('invoice', fn (Builder $invoice) => $invoice->whereInsensitiveLike('invoice_number', $search));
                        }
                        $studentQuery = fn (Builder $student) => $student
                        ->whereInsensitiveLike('first_name', $search)
                        ->orWhereInsensitiveLike('last_name', $search)
                        ->orWhereInsensitiveLike('student_number', $search);
                        $canViewFinancials
                            ? $distributions->orWhereHas('student', $studentQuery)
                            : $distributions->whereHas('student', $studentQuery);
                    });
                })
                ->when($filters['academic_year_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
                ->when($filters['grade_id'] ?? null, fn (Builder $query, int $id) => $query->where('grade_id', $id))
                ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('distributed_on', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('distributed_on', '<=', $date))
                ->when($filters['distribution_ids'] ?? [], fn (Builder $query, array $distributionIds) => $query->whereKey($distributionIds))
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
                ->when($filters['payment_ids'] ?? [], fn (Builder $query, array $paymentIds) => $query->whereKey($paymentIds))
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
                ->when($filters['student_ids'] ?? [], fn (Builder $query, array $studentIds) => $query->whereKey($studentIds))
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
            ->when($filters['registration_ids'] ?? [], fn (Builder $query, array $registrationIds) => $query->whereKey($registrationIds))
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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'student_fields' => ['nullable', 'array'],
            'student_fields.*' => ['required', 'string', Rule::in(array_keys(self::STUDENT_INFORMATION_FIELDS))],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'registration_ids' => ['nullable', 'array'],
            'registration_ids.*' => ['required', 'integer', 'distinct', 'exists:student_registrations,id'],
            'book_ids' => ['nullable', 'array'],
            'book_ids.*' => ['required', 'integer', 'distinct', 'exists:books,id'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['required', 'integer', 'distinct', 'exists:invoices,id'],
            'distribution_ids' => ['nullable', 'array'],
            'distribution_ids.*' => ['required', 'integer', 'distinct', 'exists:book_distributions,id'],
            'payment_ids' => ['nullable', 'array'],
            'payment_ids.*' => ['required', 'integer', 'distinct', 'exists:payments,id'],
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

        $filters['student_fields'] = array_values(array_unique($filters['student_fields'] ?? []));
        $filters['student_ids'] = array_values(array_unique(array_map('intval', $filters['student_ids'] ?? [])));
        $filters['registration_ids'] = array_values(array_unique(array_map('intval', $filters['registration_ids'] ?? [])));
        $filters['book_ids'] = array_values(array_unique(array_map('intval', $filters['book_ids'] ?? [])));
        $filters['invoice_ids'] = array_values(array_unique(array_map('intval', $filters['invoice_ids'] ?? [])));
        $filters['distribution_ids'] = array_values(array_unique(array_map('intval', $filters['distribution_ids'] ?? [])));
        $filters['payment_ids'] = array_values(array_unique(array_map('intval', $filters['payment_ids'] ?? [])));

        if ($request->string('report')->toString() === 'student_information'
            && $request->has('student_fields_selection')
            && $filters['student_fields'] === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'student_fields' => 'Select at least one student information field.',
            ]);
        }

        return $filters;
    }

    private function selectionKey(string $report): ?string
    {
        return match ($report) {
            'stock' => 'book_ids',
            'invoices' => 'invoice_ids',
            'distributions' => 'distribution_ids',
            'payments' => 'payment_ids',
            'student_information' => 'student_ids',
            'students', 'class_sections' => 'registration_ids',
            default => null,
        };
    }

    private function normaliseReport(string $report, bool $canViewFinancials): string
    {
        $report = in_array($report, ['students', 'student_information', 'class_sections', 'stock', 'invoices', 'distributions', 'payments'], true)
            ? $report
            : 'students';
        abort_if(! $canViewFinancials && in_array($report, ['invoices', 'payments'], true), 403);

        return $report;
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
            isset($filters['student_fields']) && $filters['student_fields'] !== [] ? 'Student fields: '.implode(', ', $this->studentInformationFields($filters)) : null,
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

    /** @param array<string, mixed> $filters
     *  @return array<string, string>
     */
    private function studentInformationFields(array $filters): array
    {
        $selected = $filters['student_fields'] ?? [];
        if ($selected === []) {
            $selected = array_keys(self::STUDENT_INFORMATION_FIELDS);
        }

        return array_intersect_key(self::STUDENT_INFORMATION_FIELDS, array_flip($selected));
    }

    /** @return array<string, string|null> */
    private function studentInformationRow(Student $student, ?StudentRegistration $registration): array
    {
        return [
            'student_number' => $student->student_number,
            'registration_number' => $student->registration_number,
            'full_name' => $student->full_name,
            'gender' => $student->gender ? (string) str($student->gender)->headline() : null,
            'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
            'phone' => $student->phone,
            'email' => $student->email,
            'enrollment_date' => $student->enrollment_date?->format('Y-m-d'),
            'latest_class' => $registration ? trim(implode(' ', array_filter([$registration->academicYear?->name, $registration->grade?->name, $registration->section?->name]))) : null,
        ];
    }
}
