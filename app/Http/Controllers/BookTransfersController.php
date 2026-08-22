<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookTransfer;
use App\Models\Branch;
use App\Models\ClassBookList;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BookTransferService;
use App\Services\BranchContext;
use App\Services\ClassBookListTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookTransfersController extends Controller
{
    public function index(Request $request, BranchContext $context): View
    {
        $branchId = $context->id();
        $canViewAllBranches = $this->canViewAllBranches($request->user(), $context);
        $showAllBranches = $canViewAllBranches && $request->boolean('all_branches');
        $query = BookTransfer::query()
            ->with('sourceBranch', 'destinationBranch', 'sourceUser', 'destinationUser', 'items.book', 'financialInvoice');

        if (! $showAllBranches) {
            $query->where(fn ($transfers) => $transfers->where('source_branch_id', $branchId)->orWhere('destination_branch_id', $branchId));
        }

        $transfers = $query->latest('transferred_on')->latest('id')->paginate(20)->withQueryString();

        return view('transfers.index', [
            'transfers' => $transfers,
            'canViewAllBranches' => $canViewAllBranches,
            'showAllBranches' => $showAllBranches,
            'canViewFinancials' => $request->user()?->canViewFinancials() ?? false,
        ]);
    }

    public function create(BranchContext $context): View
    {
        $branch = $context->branch();
        abort_unless($branch, 403);

        return view('transfers.form', [
            'branch' => $branch,
            'branches' => Branch::where('is_active', true)->whereKeyNot($branch->id)->orderBy('name')->get(),
            'users' => $branch->users()->where('is_active', true)->orderBy('name')->get(),
            'books' => Book::active()->stockedInBranch($branch->id)->withBranchStock($branch->id)->orderBy('title')->get(),
            'packages' => ClassBookList::query()->with('academicYear', 'grade', 'section', 'items')->where('is_active', true)->orderBy('name')->get(),
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(Request $request, BookTransferService $transfers, ClassBookListTransferService $packageTransfers, BranchContext $context): RedirectResponse
    {
        $branch = $context->branch();
        abort_unless($branch, 403);
        $data = $request->validate([
            'transfer_type' => ['required', Rule::in(['branch_to_branch', 'package_to_branch', 'branch_to_user', 'user_to_user'])],
            'destination_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'source_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'destination_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.book_id' => ['required', 'integer', 'distinct', 'exists:books,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'package_ids' => ['nullable', 'array', 'max:50'],
            'package_ids.*' => ['required', 'integer', 'distinct', 'exists:class_book_lists,id'],
            'package_quantities' => ['nullable', 'array', 'max:50'],
            'package_quantities.*' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $year = isset($data['academic_year_id']) ? AcademicYear::find($data['academic_year_id']) : null;

        if ($data['transfer_type'] === 'package_to_branch') {
            abort_unless(! empty($data['destination_branch_id']), 422, 'Choose a destination branch.');
            if (empty($data['package_ids'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['package_ids' => 'Choose at least one book package.']);
            }
            $packageQuantities = collect($data['package_ids'])->mapWithKeys(function ($packageId) use ($data): array {
                $packageId = (int) $packageId;
                $quantity = (int) ($data['package_quantities'][$packageId] ?? 0);

                return [$packageId => $quantity];
            })->all();
            if (in_array(0, $packageQuantities, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['package_quantities' => 'Enter a quantity for every selected book package.']);
            }
            $destination = Branch::query()->where('is_active', true)->findOrFail($data['destination_branch_id']);
            $transfer = $packageTransfers->transferToBranch($branch, $destination, $packageQuantities, $year, $request->user(), $data['notes'] ?? null);

            return redirect()->route('transfers.show', $transfer)->with('success', count($packageQuantities).' book package(s) and their selected quantities were transferred to '.$destination->name.'.');
        }

        if ($data['transfer_type'] === 'branch_to_branch') {
            abort_unless(! empty($data['destination_branch_id']), 422, 'Choose a destination branch.');
            $destination = Branch::query()->where('is_active', true)->findOrFail($data['destination_branch_id']);
            $transfer = $transfers->betweenBranches($branch, $destination, $data['items'] ?? [], $year, $request->user(), $data['notes'] ?? null);
        } elseif ($data['transfer_type'] === 'branch_to_user') {
            abort_unless(! empty($data['destination_user_id']), 422, 'Choose the destination user.');
            $transfer = $transfers->assignToUser($branch, User::findOrFail($data['destination_user_id']), $data['items'] ?? [], $year, $request->user(), $data['notes'] ?? null);
        } else {
            abort_unless(! empty($data['source_user_id']) && ! empty($data['destination_user_id']), 422, 'Choose the source and destination users.');
            $transfer = $transfers->betweenUsers($branch, User::findOrFail($data['source_user_id']), User::findOrFail($data['destination_user_id']), $data['items'] ?? [], $year, $request->user(), $data['notes'] ?? null);
        }

        return redirect()->route('transfers.show', $transfer)->with('success', 'Book transfer completed and recorded in both ledgers.');
    }

    public function show(Request $request, BookTransfer $transfer, BranchContext $context): View
    {
        abort_unless($this->canAccessTransfer($request->user(), $transfer, $context), 404);
        $transfer->load('sourceBranch', 'destinationBranch', 'sourceUser', 'destinationUser', 'academicYear', 'items.book', 'createdBy', 'reversedBy', 'financialInvoice');

        return view('transfers.show', [
            'transfer' => $transfer,
            'canViewFinancials' => $request->user()?->canViewFinancials() ?? false,
        ]);
    }

    public function reverse(Request $request, BookTransfer $transfer, BookTransferService $transfers, BranchContext $context): RedirectResponse
    {
        abort_unless($this->canAccessTransfer($request->user(), $transfer, $context), 404);
        $data = $request->validate(['reversal_notes' => ['nullable', 'string', 'max:3000']]);

        $transfers->reverse($transfer, $request->user(), $data['reversal_notes'] ?? null);

        return redirect()->route('transfers.show', $transfer)->with('success', 'Book transfer reversed and recorded in both ledgers.');
    }

    public function printInvoice(Request $request, BookTransfer $transfer, BranchContext $context): View
    {
        abort_unless($this->canAccessTransfer($request->user(), $transfer, $context), 404);
        abort_unless($request->user()?->canViewFinancials(), 403);

        $transfer->load(
            'sourceBranch',
            'destinationBranch',
            'createdBy',
            'financialInvoice.items.book',
            'financialInvoice.issuedBy',
            'financialInvoice.voidedBy',
        );
        $invoice = $transfer->financialInvoice;
        abort_unless($invoice, 404);

        $school = SystemSetting::query()
            ->whereIn('key', ['school_name', 'school_address', 'school_phone'])
            ->pluck('value', 'key');

        return view('transfers.invoice', compact('transfer', 'invoice', 'school'));
    }

    private function canAccessTransfer(?User $user, BookTransfer $transfer, BranchContext $context): bool
    {
        if ($this->canViewAllBranches($user, $context)) {
            return true;
        }

        $branchId = $context->id();

        return $transfer->source_branch_id === $branchId || $transfer->destination_branch_id === $branchId;
    }

    private function canViewAllBranches(?User $user, BranchContext $context): bool
    {
        return $user?->isControllerAdministrator() === true && $context->branch()?->code === 'MAIN';
    }
}
