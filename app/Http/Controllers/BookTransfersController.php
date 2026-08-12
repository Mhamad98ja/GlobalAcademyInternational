<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookTransfer;
use App\Models\Branch;
use App\Models\User;
use App\Services\BookTransferService;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookTransfersController extends Controller
{
    public function index(BranchContext $context): View
    {
        $branchId = $context->id();
        $transfers = BookTransfer::query()
            ->with('sourceBranch', 'destinationBranch', 'sourceUser', 'destinationUser', 'items.book')
            ->where(fn ($query) => $query->where('source_branch_id', $branchId)->orWhere('destination_branch_id', $branchId))
            ->latest('transferred_on')->latest('id')->paginate(20);

        return view('transfers.index', compact('transfers'));
    }

    public function create(BranchContext $context): View
    {
        $branch = $context->branch();
        abort_unless($branch, 403);

        return view('transfers.form', [
            'branch' => $branch,
            'branches' => Branch::where('is_active', true)->whereKeyNot($branch->id)->orderBy('name')->get(),
            'users' => $branch->users()->where('is_active', true)->orderBy('name')->get(),
            'books' => Book::active()->withBranchStock()->orderBy('title')->get(),
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(Request $request, BookTransferService $transfers, BranchContext $context): RedirectResponse
    {
        $branch = $context->branch();
        abort_unless($branch, 403);
        $data = $request->validate([
            'transfer_type' => ['required', Rule::in(['branch_to_branch', 'branch_to_user', 'user_to_user'])],
            'destination_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'source_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'destination_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.book_id' => ['required', 'integer', 'distinct', 'exists:books,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $year = isset($data['academic_year_id']) ? AcademicYear::find($data['academic_year_id']) : null;

        if ($data['transfer_type'] === 'branch_to_branch') {
            abort_unless(! empty($data['destination_branch_id']), 422, 'Choose a destination branch.');
            $destination = Branch::query()->where('is_active', true)->findOrFail($data['destination_branch_id']);
            $transfer = $transfers->betweenBranches($branch, $destination, $data['items'], $year, $request->user(), $data['notes'] ?? null);
        } elseif ($data['transfer_type'] === 'branch_to_user') {
            abort_unless(! empty($data['destination_user_id']), 422, 'Choose the destination user.');
            $transfer = $transfers->assignToUser($branch, User::findOrFail($data['destination_user_id']), $data['items'], $year, $request->user(), $data['notes'] ?? null);
        } else {
            abort_unless(! empty($data['source_user_id']) && ! empty($data['destination_user_id']), 422, 'Choose the source and destination users.');
            $transfer = $transfers->betweenUsers($branch, User::findOrFail($data['source_user_id']), User::findOrFail($data['destination_user_id']), $data['items'], $year, $request->user(), $data['notes'] ?? null);
        }

        return redirect()->route('transfers.show', $transfer)->with('success', 'Book transfer completed and recorded in both ledgers.');
    }

    public function show(BookTransfer $transfer, BranchContext $context): View
    {
        $branchId = $context->id();
        abort_unless($transfer->source_branch_id === $branchId || $transfer->destination_branch_id === $branchId, 404);
        $transfer->load('sourceBranch', 'destinationBranch', 'sourceUser', 'destinationUser', 'academicYear', 'items.book', 'createdBy');

        return view('transfers.show', compact('transfer'));
    }
}
