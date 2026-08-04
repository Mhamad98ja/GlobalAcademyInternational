<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StudentRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        [$report, $rows] = $this->reportData($request->string('report')->toString());

        return view('reports.index', compact('report', 'rows'));
    }

    public function print(Request $request): View
    {
        [$report, $rows] = $this->reportData($request->string('report')->toString());

        return view('reports.print', compact('report', 'rows'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $report = $this->normaliseReport($request->string('report')->toString());

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');

            if ($report === 'stock') {
                fputcsv($output, ['SKU', 'Title', 'On hand', 'Reserved', 'Available']);
                foreach (Book::query()->orderBy('title')->cursor() as $book) {
                    fputcsv($output, [$book->sku, $book->title, $book->stock_on_hand, $book->reserved_quantity, $book->available_quantity]);
                }
            } elseif ($report === 'invoices') {
                fputcsv($output, ['Invoice', 'Student', 'Date', 'Total', 'Paid', 'Balance', 'Payment status']);
                foreach (Invoice::query()->with('student')->latest('invoice_date')->cursor() as $invoice) {
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
                foreach (Payment::query()->with('student', 'invoice')->latest('payment_date')->cursor() as $payment) {
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
                foreach (StudentRegistration::query()->with('student', 'academicYear', 'grade', 'section')->latest()->cursor() as $registration) {
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

    /** @return array{0: string, 1: \Illuminate\Support\Collection<int, mixed>} */
    private function reportData(string $requestedReport): array
    {
        $report = $this->normaliseReport($requestedReport);

        $rows = match ($report) {
            'stock' => Book::query()->orderBy('stock_on_hand')->orderBy('title')->get(),
            'invoices' => Invoice::query()->with('student')->latest('invoice_date')->get(),
            'payments' => Payment::query()->with('student', 'invoice')->latest('payment_date')->get(),
            default => StudentRegistration::query()->with('student', 'academicYear', 'grade', 'section')->latest()->get(),
        };

        return [$report, $rows];
    }

    private function normaliseReport(string $report): string
    {
        return in_array($report, ['students', 'stock', 'invoices', 'payments'], true) ? $report : 'students';
    }
}
