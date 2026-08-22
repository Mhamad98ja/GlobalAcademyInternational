<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookReturn;
use App\Models\Branch;
use App\Models\BranchTransferInvoice;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupplierPayment;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Read-only financial register. Student revenue, supplier liabilities, and
 * inter-branch valuation remain separate; an internal transfer is never income.
 */
class OperationsController extends Controller
{
    public function index(Request $request, BranchContext $branchContext): View
    {
        $user = $request->user();
        abort_unless($user?->canViewFinancials(), 403);

        $filters = $request->validate([
            'tab' => ['nullable', 'in:all,students,suppliers,branches'],
            'scope' => ['nullable', 'in:current,all'],
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $tab = $filters['tab'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $canViewAllBranches = $user->isControllerAdministrator();
        $showAllBranches = $canViewAllBranches && ($filters['scope'] ?? 'current') === 'all';
        $activeBranchId = $branchContext->id();
        $branchIds = ($showAllBranches
            ? Branch::query()->pluck('id')
            : collect([$activeBranchId])->filter()
        )->values();
        $branches = Branch::query()->whereIn('id', $branchIds)->get()->keyBy('id');

        $canViewInvoices = $user->hasPermission('invoices.view');
        $canViewPayments = $user->hasPermission('payments.view');
        $canViewStudents = $canViewInvoices || $canViewPayments;
        $canViewSuppliers = $user->hasPermission('purchases.manage');
        $canViewReturns = $user->hasPermission('returns.manage');
        $canViewTransfers = $user->hasPermission('inventory.transfer');
        $limit = 150;

        $applyDateRange = static function (Builder $query, string $column) use ($dateFrom, $dateTo): Builder {
            if ($dateFrom) {
                $query->whereDate($column, '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate($column, '<=', $dateTo);
            }

            return $query;
        };
        $branchScoped = static function (Builder $query) use ($branchIds): Builder {
            return $query
                ->withoutGlobalScope('branch')
                ->whereIn($query->getModel()->getTable().'.branch_id', $branchIds);
        };

        $invoiceQuery = $applyDateRange($branchScoped(Invoice::query()), 'invoices.invoice_date')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $invoices) use ($search): void {
                    $invoices->whereInsensitiveLike('invoices.invoice_number', $search)
                        ->orWhereHas('student', fn (Builder $students) => $students
                            ->withoutGlobalScope('branch')
                            ->where(fn (Builder $students) => $students
                                ->whereInsensitiveLike('first_name', $search)
                                ->orWhereInsensitiveLike('last_name', $search)));
                });
            });

        $paymentQuery = $applyDateRange($branchScoped(Payment::query()), 'payments.payment_date')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $payments) use ($search): void {
                    $payments->whereInsensitiveLike('payments.payment_number', $search)
                        ->orWhereInsensitiveLike('payments.reference_number', $search)
                        ->orWhereHas('invoice', fn (Builder $invoices) => $invoices
                            ->withoutGlobalScope('branch')
                            ->whereInsensitiveLike('invoice_number', $search))
                        ->orWhereHas('student', fn (Builder $students) => $students
                            ->withoutGlobalScope('branch')
                            ->where(fn (Builder $students) => $students
                                ->whereInsensitiveLike('first_name', $search)
                                ->orWhereInsensitiveLike('last_name', $search)));
                });
            });

        $purchaseQuery = $applyDateRange($branchScoped(SupplierPurchase::query()), 'supplier_purchases.purchase_date')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $purchases) use ($search): void {
                    $purchases->whereInsensitiveLike('supplier_purchases.purchase_number', $search)
                        ->orWhereInsensitiveLike('supplier_purchases.supplier_invoice_number', $search)
                        ->orWhereHas('supplier', fn (Builder $suppliers) => $suppliers->whereInsensitiveLike('name', $search));
                });
            });

        $supplierPaymentQuery = $applyDateRange($branchScoped(SupplierPayment::query()), 'supplier_payments.paid_on')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $payments) use ($search): void {
                    $payments->whereInsensitiveLike('supplier_payments.reference_number', $search)
                        ->orWhereHas('purchase', fn (Builder $purchases) => $purchases
                            ->withoutGlobalScope('branch')
                            ->whereInsensitiveLike('purchase_number', $search));
                });
            });

        $supplierReturnQuery = $applyDateRange($branchScoped(SupplierReturn::query()), 'supplier_returns.returned_on')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $returns) use ($search): void {
                    $returns->whereInsensitiveLike('supplier_returns.reference_number', $search)
                        ->orWhereHas('supplier', fn (Builder $suppliers) => $suppliers->whereInsensitiveLike('name', $search))
                        ->orWhereHas('purchase', fn (Builder $purchases) => $purchases
                            ->withoutGlobalScope('branch')
                            ->whereInsensitiveLike('purchase_number', $search));
                });
            });

        $studentReturnQuery = $applyDateRange($branchScoped(BookReturn::query()), 'book_returns.returned_on')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $returns) use ($search): void {
                    $returns->whereHas('invoice', fn (Builder $invoices) => $invoices
                        ->withoutGlobalScope('branch')
                        ->whereInsensitiveLike('invoice_number', $search))
                        ->orWhereHas('student', fn (Builder $students) => $students
                            ->withoutGlobalScope('branch')
                            ->where(fn (Builder $students) => $students
                                ->whereInsensitiveLike('first_name', $search)
                                ->orWhereInsensitiveLike('last_name', $search)));
                });
            });

        $transferInvoiceQuery = $applyDateRange(
            BranchTransferInvoice::query()
                ->when(! $showAllBranches, function (Builder $query) use ($activeBranchId): void {
                    $query->where(fn (Builder $transfers) => $transfers
                        ->where('source_branch_id', $activeBranchId)
                        ->orWhere('destination_branch_id', $activeBranchId));
                })
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $transfers) use ($search): void {
                        $transfers->whereInsensitiveLike('invoice_number', $search)
                            ->orWhereHas('sourceBranch', fn (Builder $branches) => $branches->whereInsensitiveLike('name', $search))
                            ->orWhereHas('destinationBranch', fn (Builder $branches) => $branches->whereInsensitiveLike('name', $search));
                    });
                }),
            'branch_transfer_invoices.issued_on',
        );

        $studentSummary = [
            'invoiced' => $canViewInvoices ? (float) (clone $invoiceQuery)->where('status', '!=', 'cancelled')->sum('total') : null,
            'collected' => $canViewPayments ? (float) (clone $paymentQuery)->where('status', 'valid')->sum('amount') : null,
            'outstanding' => $canViewInvoices ? (float) (clone $invoiceQuery)->where('status', '!=', 'cancelled')->sum('balance_due') : null,
            'refunds' => $canViewReturns ? (float) (clone $studentReturnQuery)->sum('refund_total') : null,
        ];
        $supplierSummary = [
            'purchased' => $canViewSuppliers ? (float) (clone $purchaseQuery)->sum('total') : null,
            'paid' => $canViewSuppliers ? (float) (clone $purchaseQuery)->sum('amount_paid') : null,
            'credits' => $canViewSuppliers ? (float) (clone $supplierReturnQuery)->sum('credit_total') : null,
        ];
        $supplierSummary['outstanding'] = $canViewSuppliers
            ? max(0, $supplierSummary['purchased'] - $supplierSummary['paid'] - $supplierSummary['credits'])
            : null;
        $issuedTransferQuery = (clone $transferInvoiceQuery)->where('status', 'issued');
        $branchSummary = [
            'documents' => $canViewTransfers ? (clone $issuedTransferQuery)->count() : null,
            'moved' => $canViewTransfers ? (float) (clone $issuedTransferQuery)->sum('total') : null,
            'sent' => $canViewTransfers && ! $showAllBranches ? (float) (clone $issuedTransferQuery)->where('source_branch_id', $activeBranchId)->sum('total') : null,
            'received' => $canViewTransfers && ! $showAllBranches ? (float) (clone $issuedTransferQuery)->where('destination_branch_id', $activeBranchId)->sum('total') : null,
        ];

        $records = collect();
        if ($canViewInvoices && in_array($tab, ['all', 'students'], true)) {
            $records = $records->merge(
                (clone $invoiceQuery)->with(['student' => fn ($students) => $students->withoutGlobalScope('branch')])
                    ->latest('invoice_date')->limit($limit)->get()
                    ->map(fn (Invoice $invoice) => $this->record('students', 'Student sale invoice', $invoice->invoice_number, $invoice->student?->full_name ?? 'Student record unavailable', $invoice->status === 'cancelled' ? 'Cancelled invoice' : 'Books sold to student', $branches->get($invoice->branch_id)?->name, $invoice->invoice_date, (float) $invoice->total, 0, 0, (float) $invoice->balance_due, $invoice->status === 'cancelled' ? 'cancelled' : $invoice->payment_status, route('invoices.show', $invoice)))
            );
        }
        if ($canViewPayments && in_array($tab, ['all', 'students'], true)) {
            $records = $records->merge(
                (clone $paymentQuery)->with([
                    'student' => fn ($students) => $students->withoutGlobalScope('branch'),
                    'invoice' => fn ($invoices) => $invoices->withoutGlobalScope('branch'),
                ])->latest('payment_date')->limit($limit)->get()
                    ->map(fn (Payment $payment) => $this->record('students', 'Student payment', $payment->payment_number, $payment->student?->full_name ?? 'Student record unavailable', 'For invoice '.($payment->invoice?->invoice_number ?? '—'), $branches->get($payment->branch_id)?->name, $payment->payment_date, (float) $payment->amount, $payment->status === 'valid' ? (float) $payment->amount : 0, 0, null, $payment->status, route('payments.receipt', $payment)))
            );
        }
        if ($canViewReturns && in_array($tab, ['all', 'students'], true)) {
            $records = $records->merge(
                (clone $studentReturnQuery)->with([
                    'student' => fn ($students) => $students->withoutGlobalScope('branch'),
                    'invoice' => fn ($invoices) => $invoices->withoutGlobalScope('branch'),
                ])->latest('returned_on')->limit($limit)->get()
                    ->map(fn (BookReturn $return) => $this->record('students', 'Student refund / return', 'RETURN-'.$return->id, $return->student?->full_name ?? 'Student record unavailable', 'For invoice '.($return->invoice?->invoice_number ?? '—'), $branches->get($return->branch_id)?->name, $return->returned_on, (float) $return->refund_total, 0, (float) $return->refund_total, null, $return->status, route('returns.show', $return)))
            );
        }
        if ($canViewSuppliers && in_array($tab, ['all', 'suppliers'], true)) {
            $records = $records->merge(
                (clone $purchaseQuery)->with('supplier')->withSum('supplierReturns as supplier_returns_credit_total_sum', 'credit_total')
                    ->latest('purchase_date')->limit($limit)->get()
                    ->map(fn (SupplierPurchase $purchase) => $this->record('suppliers', 'Supplier purchase invoice', $purchase->purchase_number, $purchase->supplier?->name ?? 'Supplier record unavailable', $purchase->supplier_invoice_number ? 'Supplier invoice: '.$purchase->supplier_invoice_number : 'No supplier invoice number', $branches->get($purchase->branch_id)?->name, $purchase->purchase_date, (float) $purchase->total, 0, 0, (float) $purchase->balance_due, $purchase->payment_status, route('purchases.show', $purchase)))
            );
            $records = $records->merge(
                (clone $supplierPaymentQuery)->with(['purchase' => fn ($purchases) => $purchases->withoutGlobalScope('branch')->with('supplier')])
                    ->latest('paid_on')->limit($limit)->get()
                    ->map(fn (SupplierPayment $payment) => $this->record('suppliers', 'Supplier payment', $payment->reference_number ?: 'SUP-PAY-'.$payment->id, $payment->purchase?->supplier?->name ?? 'Supplier record unavailable', 'For purchase '.($payment->purchase?->purchase_number ?? '—'), $branches->get($payment->branch_id)?->name, $payment->paid_on, (float) $payment->amount, 0, (float) $payment->amount, null, $payment->method, route('purchases.show', $payment->purchase)))
            );
            $records = $records->merge(
                (clone $supplierReturnQuery)->with([
                    'supplier',
                    'purchase' => fn ($purchases) => $purchases->withoutGlobalScope('branch'),
                ])->latest('returned_on')->limit($limit)->get()
                    ->map(fn (SupplierReturn $return) => $this->record('suppliers', 'Supplier credit', $return->reference_number ?: 'SUP-RETURN-'.$return->id, $return->supplier?->name ?? 'Supplier record unavailable', 'Credit for purchase '.($return->purchase?->purchase_number ?? '—'), $branches->get($return->branch_id)?->name, $return->returned_on, (float) $return->credit_total, 0, 0, null, $return->status, route('supplier-returns.show', $return)))
            );
        }
        if ($canViewTransfers && in_array($tab, ['all', 'branches'], true)) {
            $records = $records->merge(
                (clone $transferInvoiceQuery)->with('sourceBranch', 'destinationBranch', 'transfer')
                    ->latest('issued_on')->limit($limit)->get()
                    ->map(fn (BranchTransferInvoice $invoice) => $this->record('branches', 'Internal branch transfer', $invoice->invoice_number, ($invoice->sourceBranch?->name ?? 'Source branch').' → '.($invoice->destinationBranch?->name ?? 'Destination branch'), 'Internal stock value only — not revenue or supplier expense', $showAllBranches ? 'Inter-branch' : ($invoice->source_branch_id === $activeBranchId ? 'Sent' : 'Received'), $invoice->issued_on, (float) $invoice->total, 0, 0, null, $invoice->status, route('transfers.invoice', $invoice->transfer)))
            );
        }

        $records = $records->sortByDesc(fn (array $record) => $record['date']->format('Y-m-d'))->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $transactions = new LengthAwarePaginator(
            $records->forPage($page, 25)->values(),
            $records->count(),
            25,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('operations.index', compact(
            'transactions', 'tab', 'search', 'dateFrom', 'dateTo', 'showAllBranches', 'canViewAllBranches',
            'canViewStudents', 'canViewInvoices', 'canViewPayments', 'canViewSuppliers', 'canViewTransfers',
            'canViewReturns', 'studentSummary', 'supplierSummary', 'branchSummary',
        ));
    }

    /** @return array<string, mixed> */
    private function record(string $category, string $kind, string $reference, string $party, string $detail, ?string $branch, mixed $date, float $value, float $cashIn, float $cashOut, ?float $balance, string $status, string $href): array
    {
        return compact('category', 'kind', 'reference', 'party', 'detail', 'branch', 'date', 'value', 'cashIn', 'cashOut', 'balance', 'status', 'href');
    }
}
