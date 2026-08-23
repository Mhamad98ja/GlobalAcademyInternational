<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_transfers', function (Blueprint $table): void {
            $table->foreignId('accepted_by')->nullable()->after('completed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            $table->text('receipt_notes')->nullable()->after('accepted_at');
            $table->foreignId('rejected_by')->nullable()->after('receipt_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->index(['destination_branch_id', 'status'], 'transfer_destination_status_index');
        });

        Schema::table('branch_transfer_invoices', function (Blueprint $table): void {
            $table->foreignId('accepted_by')->nullable()->after('issued_by')->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            $table->foreignId('rejected_by')->nullable()->after('accepted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->string('settlement_status')->default('legacy_unsettled')->index()->after('status');
            $table->timestamp('settled_at')->nullable()->after('settlement_status');
            $table->foreignId('settled_by')->nullable()->after('settled_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('branch_cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('branch_cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('movement_number')->unique();
            $table->foreignId('branch_cash_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('counterparty_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('book_transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_transfer_invoice_id')->constrained()->restrictOnDelete();
            $table->string('type')->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->date('settled_on');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_transfer_invoice_id', 'branch_id', 'type'], 'branch_cash_movement_invoice_branch_type_unique');
            $table->index(['branch_id', 'settled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_cash_movements');
        Schema::dropIfExists('branch_cash_accounts');

        Schema::table('branch_transfer_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settled_by');
            $table->dropColumn(['settlement_status', 'settled_at']);
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn('accepted_at');
        });

        Schema::table('book_transfers', function (Blueprint $table): void {
            $table->dropIndex('transfer_destination_status_index');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn(['accepted_at', 'receipt_notes']);
        });
    }
};
