<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BookTransfer;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\BranchTransferStatusNotification;
use Illuminate\Support\Collection;

final class BranchTransferNotificationService
{
    public function pendingReceipt(BookTransfer $transfer): void
    {
        $transfer->loadMissing('sourceBranch', 'destinationBranch', 'financialInvoice');
        if ($transfer->destinationBranch === null) {
            return;
        }

        $this->notify($this->transferUsers($transfer->destinationBranch), $this->payload(
            $transfer,
            'pending_receipt',
            'Incoming transfer awaiting confirmation',
            sprintf('%s sent %s. Confirm the exact quantities received before stock and branch settlement are posted.', $transfer->sourceBranch?->name ?? 'A branch', $transfer->transfer_number),
        ));
    }

    public function accepted(BookTransfer $transfer): void
    {
        $transfer->loadMissing('sourceBranch', 'destinationBranch', 'financialInvoice', 'createdBy');
        if ($transfer->sourceBranch === null) {
            return;
        }

        $users = $this->transferUsers($transfer->sourceBranch);
        if ($transfer->createdBy !== null) {
            $users->push($transfer->createdBy);
        }
        $this->notify($users, $this->payload(
            $transfer,
            'accepted',
            'Transfer accepted and settled',
            sprintf('%s accepted %s. Destination stock and the internal branch settlement are now posted.', $transfer->destinationBranch?->name ?? 'The receiving branch', $transfer->transfer_number),
        ));
    }

    public function rejected(BookTransfer $transfer): void
    {
        $transfer->loadMissing('sourceBranch', 'destinationBranch', 'financialInvoice', 'createdBy');
        if ($transfer->sourceBranch === null) {
            return;
        }

        $users = $this->transferUsers($transfer->sourceBranch);
        if ($transfer->createdBy !== null) {
            $users->push($transfer->createdBy);
        }
        $this->notify($users, $this->payload(
            $transfer,
            'rejected',
            'Transfer rejected and stock returned',
            sprintf('%s rejected %s. The dispatched stock was restored to the sending branch and no settlement was posted.', $transfer->destinationBranch?->name ?? 'The receiving branch', $transfer->transfer_number),
        ));
    }

    public function cancelled(BookTransfer $transfer): void
    {
        $transfer->loadMissing('sourceBranch', 'destinationBranch', 'financialInvoice');
        if ($transfer->destinationBranch === null) {
            return;
        }

        $this->notify($this->transferUsers($transfer->destinationBranch), $this->payload(
            $transfer,
            'cancelled',
            'Incoming transfer cancelled',
            sprintf('%s cancelled %s before receipt. No destination stock or settlement was posted.', $transfer->sourceBranch?->name ?? 'The sending branch', $transfer->transfer_number),
        ));
    }

    /** @return Collection<int, User> */
    private function transferUsers(Branch $branch): Collection
    {
        $branchUsers = $branch->users()
            ->where('users.is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('inventory.transfer') || $user->isControllerAdministrator());

        $controllerUsers = User::query()
            ->where('is_active', true)
            ->where('is_controller_administrator', true)
            ->get();

        return $branchUsers->merge($controllerUsers)->unique('id')->values();
    }

    /** @param Collection<int, User> $users */
    private function notify(Collection $users, array $payload): void
    {
        $users->unique('id')->each(fn (User $user) => $user->notify(new BranchTransferStatusNotification($payload)));
    }

    /** @return array<string, mixed> */
    private function payload(BookTransfer $transfer, string $status, string $title, string $message): array
    {
        return [
            'category' => 'branch_transfer',
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'transfer_number' => $transfer->transfer_number,
            'invoice_number' => $transfer->financialInvoice?->invoice_number,
            'amount' => $transfer->financialInvoice?->total,
            'action_url' => route('transfers.show', $transfer),
        ];
    }
}
