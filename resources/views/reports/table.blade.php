<div class="table-wrap">
    @if ($rows->isEmpty())
        <div class="empty">There are no records for this report yet.</div>
    @elseif ($report === 'stock')
        <table class="data-table">
            <thead><tr><th>SKU</th><th>Book</th><th>On hand</th><th>Reserved</th><th>Available</th></tr></thead>
            <tbody>
                @foreach ($rows as $book)
                    <tr><td>{{ $book->sku }}</td><td>{{ $book->title }}</td><td>{{ $book->stock_on_hand }}</td><td>{{ $book->reserved_quantity }}</td><td>{{ $book->available_quantity }}</td></tr>
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
    @else
        <table class="data-table">
            <thead><tr><th>Student</th><th>Academic year</th><th>Grade</th><th>Section</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($rows as $registration)
                    <tr>
                        <td>{{ $registration->student?->full_name }}</td>
                        <td>{{ $registration->academicYear?->name }}</td>
                        <td>{{ $registration->grade?->name }}</td>
                        <td>{{ $registration->section?->name ?? '—' }}</td>
                        <td>{{ str($registration->status)->headline() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
