<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->where(function ($query): void {
                $query->whereIn('status', ['pending_deferred_approval', 'deferred'])
                    ->orWhere('payment_status', 'deferred');
            })
            ->orderBy('id')
            ->eachById(function (object $invoice): void {
                $balance = (float) $invoice->balance_due;
                $paid = (float) $invoice->amount_paid;

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'status' => 'issued',
                    'payment_status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
                    'updated_at' => now(),
                ]);
            });

        DB::table('notifications')
            ->where('type', 'App\\Notifications\\DeferredPaymentStatusNotification')
            ->delete();
        DB::table('audit_logs')->where('event', 'like', 'deferred_payment.%')->delete();

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['deferred.view', 'deferred.create', 'deferred.approve'])
            ->pluck('id');
        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Storage::deleteDirectory('private/deferred-approvals');
        Schema::dropIfExists('deferred_payment_approvals');
    }

    public function down(): void
    {
        // Deferred approvals, their supporting documents, and their permissions
        // are intentionally removed and cannot be reconstructed on rollback.
    }
};
