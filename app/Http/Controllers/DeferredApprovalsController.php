<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DeferredPaymentApproval;
use App\Models\Invoice;
use App\Services\DeferredPaymentApprovalService;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DeferredApprovalsController extends Controller
{
    public function index(Request $request, BranchContext $context): View
    {
        $user = $request->user();
        $branchId = $context->id();
        $canCreate = $user->hasPermission('deferred.create');

        return view('deferred.index', [
            'approvals' => DeferredPaymentApproval::query()
                ->with('student', 'invoice', 'createdBy', 'administrator')
                ->latest()
                ->paginate(15),
            'invoices' => $canCreate
                ? Invoice::query()
                    ->with('student')
                    ->where('balance_due', '>', 0)
                    ->whereNotIn('status', ['cancelled', 'pending_deferred_approval', 'deferred'])
                    ->latest()
                    ->take(100)
                    ->get()
                : collect(),
            'canCreate' => $canCreate,
            'canApprove' => $user->hasPermission('deferred.approve') && $user->canManageUsersInBranch($branchId),
        ]);
    }

    public function store(Request $request, DeferredPaymentApprovalService $approvals): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'expected_payment_date' => ['nullable', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'document' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ]);
        $invoice = Invoice::query()->findOrFail($data['invoice_id']);
        $path = $request->file('document')?->store('private/deferred-approvals');
        $approvals->requestForInvoice($invoice, $request->user(), [
            ...$data,
            'document_path' => $path,
        ]);

        return back()->with('success', 'Deferred-payment request submitted to the branch administrator.');
    }

    public function approve(Request $request, DeferredPaymentApproval $approval, DeferredPaymentApprovalService $approvals): RedirectResponse
    {
        abort_unless($request->user()->canManageUsersInBranch($approval->branch_id), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_notes' => ['nullable', 'string', 'max:3000', 'required_if:decision,rejected'],
        ]);

        $approvals->decide($approval, $request->user(), $data['decision'], $data['decision_notes'] ?? null);

        return back()->with('success', 'Deferred-payment request '.$data['decision'].'.');
    }

    public function document(DeferredPaymentApproval $approval)
    {
        abort_unless(auth()->user()?->hasPermission('deferred.view'), 403);
        abort_unless($approval->document_path && Storage::exists($approval->document_path), 404);

        return Storage::download($approval->document_path);
    }
}
