<div class="table-wrap">
    @if ($rows->isEmpty())
        <div class="empty">There are no records for this report yet.</div>
    @elseif ($report === 'stock')
        <table class="data-table">
            <thead><tr><th>SKU</th><th>Book</th><th>On hand</th><th>Reserved</th><th>Available</th></tr></thead>
            <tbody>
                @foreach ($rows as $book)
                    @php($reserved = $book->branchStocks->first()?->reserved_quantity ?? 0)
                    @if (($filters['stock_level'] ?? null) === 'in_stock' && $book->branch_quantity < 1) @continue @endif
                    @if (($filters['stock_level'] ?? null) === 'low_stock' && $book->branch_quantity > $book->minimum_stock) @continue @endif
                    @if (($filters['stock_level'] ?? null) === 'out_of_stock' && $book->branch_quantity > 0) @continue @endif
                    <tr><td>{{ $book->sku }}</td><td>{{ $book->title }}</td><td>{{ $book->branch_quantity }}</td><td>{{ $reserved }}</td><td>{{ $book->available_quantity }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($report === 'invoices')
        <table class="data-table">
            <thead><tr><th>Invoice</th><th>Student</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($rows as $invoice)
                    <tr>
                        <td>{{ $invoice->invoice_number }}</td>
                        <td>{{ $invoice->student?->full_name }}</td>
                        <td>{{ $invoice->invoice_date?->format('d M Y') }}</td>
                        <td>{{ number_format((float) $invoice->total, 2) }}</td>
                        <td>{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                        <td>{{ number_format((float) $invoice->balance_due, 2) }}</td>
                        <td>{{ str($invoice->payment_status)->headline() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($report === 'payments')
        <table class="data-table">
            <thead><tr><th>Payment</th><th>Student</th><th>Invoice</th><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($rows as $payment)
                    <tr>
                        <td>{{ $payment->payment_number }}</td>
                        <td>{{ $payment->student?->full_name }}</td>
                        <td>{{ $payment->invoice?->invoice_number }}</td>
                        <td>{{ $payment->payment_date?->format('d M Y') }}</td>
                        <td>{{ number_format((float) $payment->amount, 2) }}</td>
                        <td>{{ str($payment->method)->headline() }}</td>
                        <td>{{ str($payment->status)->headline() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($report === 'student_information')
        <table class="data-table">
            <thead><tr><th>Student no.</th><th>Registration no.</th><th>Student</th><th>Gender</th><th>Date of birth</th><th>Phone</th><th>Email</th><th>Enrollment</th><th>Latest class</th></tr></thead>
            <tbody>
                @foreach ($rows as $student)
                    @php($registration = $student->registrations->first())
                    <tr>
                        <td>{{ $student->student_number }}</td>
                        <td>{{ $student->registration_number ?: '—' }}</td>
                        <td>{{ $student->full_name }}</td>
                        <td>{{ $student->gender ? str($student->gender)->headline() : '—' }}</td>
                        <td>{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $student->phone ?: '—' }}</td>
                        <td>{{ $student->email ?: '—' }}</td>
                        <td>{{ $student->enrollment_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $registration ? collect([$registration->academicYear?->name, $registration->grade?->name, $registration->section?->name])->filter()->implode(' · ') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($report === 'class_sections')
        <table class="data-table">
            <thead><tr><th>Academic year</th><th>Grade</th><th>Section</th><th>Student no.</th><th>Registration no.</th><th>Student</th><th>Gender</th><th>Status</th><th>Left school on</th></tr></thead>
            <tbody>
                @foreach ($rows as $registration)
                    <tr>
                        <td>{{ $registration->academicYear?->name }}</td>
                        <td>{{ $registration->grade?->name }}</td>
                        <td>{{ $registration->section?->name ?? '—' }}</td>
                        <td>{{ $registration->student?->student_number }}</td>
                        <td>{{ $registration->student?->registration_number ?: '—' }}</td>
                        <td>{{ $registration->student?->full_name }}</td>
                        <td>{{ $registration->student?->gender ? str($registration->student->gender)->headline() : '—' }}</td>
                        <td>{{ str($registration->status)->headline() }}</td>
                        <td>{{ $registration->withdrawn_on?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table class="data-table">
            <thead><tr><th>Student no.</th><th>Registration no.</th><th>Student</th><th>Academic year</th><th>Grade</th><th>Section</th><th>Registered on</th><th>Status</th><th>Left school on</th></tr></thead>
            <tbody>
                @foreach ($rows as $registration)
                    <tr>
                        <td>{{ $registration->student?->student_number }}</td>
                        <td>{{ $registration->student?->registration_number ?: '—' }}</td>
                        <td>{{ $registration->student?->full_name }}</td>
                        <td>{{ $registration->academicYear?->name }}</td>
                        <td>{{ $registration->grade?->name }}</td>
                        <td>{{ $registration->section?->name ?? '—' }}</td>
                        <td>{{ $registration->registered_on?->format('d M Y') }}</td>
                        <td>{{ str($registration->status)->headline() }}</td>
                        <td>{{ $registration->withdrawn_on?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
