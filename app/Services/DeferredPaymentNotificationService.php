<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeferredPaymentApproval;
use App\Models\User;
use App\Notifications\DeferredPaymentStatusNotification;
use Illuminate\Support\Collection;

final class DeferredPaymentNotificationService
{
    public function requested(DeferredPaymentApproval $approval): void
    {
        $approval->loadMissing('invoice', 'student', 'branch');
        $branch = $approval->branch;
        if ($branch === null) {
            return;
        }

        $users = $branch->users()
            ->where('users.is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('deferred.approve') && $user->canManageUsersInBranch($branch->id));
        $controllers = User::query()
            ->where('is_active', true)
            ->where('is_controller_administrator', true)
            ->get();

        $this->notify($users->merge($controllers), [
            'category' => 'deferred_payment',
            'status' => 'pending',
            'title' => 'Deferred payment needs branch approval',
            'message' => sprintf('%s requested deferred payment for invoice %s. Current balance: %s.', $approval->student?->full_name ?? 'A student', $approval->invoice?->invoice_number ?? '—', number_format((float) $approval->invoice?->balance_due, 2)),
            'invoice_number' => $approval->invoice?->invoice_number,
            'amount' => $approval->invoice?->balance_due,
            'action_url' => route('deferred.index'),
        ]);
    }

    public function decided(DeferredPaymentApproval $approval): void
    {
        $approval->loadMissing('invoice', 'student', 'createdBy');
        if ($approval->createdBy === null) {
            return;
        }

        $accepted = $approval->status === 'approved';
        $remaining = (float) ($approval->invoice?->balance_due ?? 0);
        $this->notify(collect([$approval->createdBy]), [
            'category' => 'deferred_payment',
            'status' => $accepted ? 'accepted' : 'rejected',
            'title' => $accepted ? 'Deferred payment accepted' : 'Deferred payment rejected',
            'message' => $accepted
                ? sprintf('Deferred payment for invoice %s was accepted. The remaining balance is %s and will reduce with every registered payment.', $approval->invoice?->invoice_number ?? '—', number_format($remaining, 2))
                : sprintf('Deferred payment for invoice %s was rejected. Payment of %s is required.', $approval->invoice?->invoice_number ?? '—', number_format($remaining, 2)),
            'invoice_number' => $approval->invoice?->invoice_number,
            'amount' => $remaining,
            'action_url' => route('notifications.index'),
        ]);
    }

    /** @param Collection<int, User> $users @param array<string, mixed> $payload */
    private function notify(Collection $users, array $payload): void
    {
        $users->unique('id')->each(fn (User $user) => $user->notify(new DeferredPaymentStatusNotification($payload)));
    }
}
