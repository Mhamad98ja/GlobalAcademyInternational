<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\ClassBookList;
use App\Models\ClassBookListItem;
use App\Models\Invoice;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BookDistributionService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PaymentService $payments,
        private readonly AuditService $audit,
    ) {
    }

    public function distribute(
        StudentRegistration $registration,
        array $items,
        array $payment,
        User $user,
        ?string $notes = null,
        ?ClassBookList $package = null,
    ): BookDistribution {
        return DB::transaction(function () use ($registration, $items, $payment, $user, $notes, $package): BookDistribution {
            $registration = StudentRegistration::query()->with('student')->lockForUpdate()->findOrFail($registration->id);
            if ($registration->status !== 'active') {
                throw ValidationException::withMessages(['registration' => 'Only active registrations can receive books.']);
            }

            $packageItems = $package?->items->keyBy('book_id') ?? collect();
            $normalized = [];
            $subtotal = 0;
            $taxTotal = 0;

            foreach ($items as $row) {
                $book = Book::query()->lockForUpdate()->findOrFail($row['book_id']);
                $quantity = (int) $row['quantity'];
                if ($quantity < 1) {
                    throw ValidationException::withMessages(['items' => 'Quantity must be at least one.']);
                }

                [$unit, $discountPerBook] = $this->priceFor($book, $packageItems->get($book->id));
                $discount = $discountPerBook * $quantity;
                $base = $unit * $quantity;
                $rate = Money::cents((string) $book->tax_rate);
                $tax = intdiv(($base * $rate) + 5000, 10000);
                $line = $base + $tax;

                $normalized[] = compact('book', 'quantity', 'unit', 'discount', 'tax', 'line');
                $subtotal += $base;
                $taxTotal += $tax;
            }

            $total = $subtotal + $taxTotal;
            $invoice = Invoice::create([
                'invoice_number' => 'INV-'.now()->format('Ymd').'-'.str_pad((string) ((Invoice::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
                'student_id' => $registration->student_id,
                'student_registration_id' => $registration->id,
                'responsible_person_id' => $registration->student->responsiblePersons()
                    ->orderByDesc('responsible_person_student.is_primary')
                    ->value('responsible_persons.id'),
                'academic_year_id' => $registration->academic_year_id,
                'grade_id' => $registration->grade_id,
                'section_id' => $registration->section_id,
                'invoice_date' => today(),
                'subtotal' => Money::decimal($subtotal),
                'discount_total' => Money::decimal(collect($normalized)->sum('discount')),
                'tax_total' => Money::decimal($taxTotal),
                'total' => Money::decimal($total),
                'amount_paid' => '0.00',
                'balance_due' => Money::decimal($total),
                'payment_status' => isset($payment['deferred']) && $payment['deferred'] ? 'deferred' : 'unpaid',
                'status' => isset($payment['deferred']) && $payment['deferred'] ? 'deferred' : 'issued',
                'notes' => $notes,
                'created_by' => $user->id,
            ]);
            $distribution = BookDistribution::create([
                'student_id' => $registration->student_id,
                'student_registration_id' => $registration->id,
                'academic_year_id' => $registration->academic_year_id,
                'grade_id' => $registration->grade_id,
                'section_id' => $registration->section_id,
                'invoice_id' => $invoice->id,
                'distributed_on' => today(),
                'status' => 'completed',
                'notes' => $notes,
                'distributed_by' => $user->id,
            ]);

            foreach ($normalized as $line) {
                $invoice->items()->create([
                    'book_id' => $line['book']->id,
                    'description' => $line['book']->title,
                    'quantity' => $line['quantity'],
                    'unit_price' => Money::decimal($line['unit']),
                    'discount_amount' => Money::decimal($line['discount']),
                    'tax_rate' => $line['book']->tax_rate,
                    'tax_amount' => Money::decimal($line['tax']),
                    'line_total' => Money::decimal($line['line']),
                ]);
                $distribution->items()->create([
                    'book_id' => $line['book']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => Money::decimal($line['unit']),
                    'discount_amount' => Money::decimal($line['discount']),
                    'tax_rate' => $line['book']->tax_rate,
                    'line_total' => Money::decimal($line['line']),
                ]);
                $this->inventory->change(
                    $line['book'],
                    -$line['quantity'],
                    'student_distribution',
                    $distribution,
                    $registration->academicYear,
                    $user,
                    'Student distribution',
                    $notes,
                );
            }

            if (! empty($payment['amount'])) {
                $this->payments->record($invoice, [
                    'amount' => $payment['amount'],
                    'payment_date' => $payment['payment_date'] ?? today()->toDateString(),
                    'method' => $payment['method'] ?? 'cash',
                    'cashbox' => $payment['cashbox'] ?? null,
                    'reference_number' => $payment['reference_number'] ?? null,
                    'idempotency_key' => $payment['idempotency_key'] ?? null,
                ], $user);
            }

            $this->audit->log('distribution.completed', $distribution, [], [
                'invoice' => $invoice->invoice_number,
                'items' => count($normalized),
                'book_package' => $package?->name,
            ]);

            return $distribution;
        });
    }

    private function priceFor(Book $book, ?ClassBookListItem $packageItem): array
    {
        $catalogUnit = Money::cents((string) $book->selling_price);
        if (! $packageItem || $packageItem->price_type === 'paid') {
            return [$catalogUnit, 0];
        }

        if ($packageItem->price_type === 'free') {
            return [0, $catalogUnit];
        }

        $packageUnit = Money::cents((string) $packageItem->unit_price);
        $unit = min($catalogUnit, max(0, $packageUnit));

        return [$unit, max(0, $catalogUnit - $unit)];
    }
}
