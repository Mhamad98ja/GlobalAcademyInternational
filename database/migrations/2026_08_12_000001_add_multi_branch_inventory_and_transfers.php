<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        $mainBranchId = DB::table('branches')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'code' => 'MAIN',
            'name' => 'Main Branch',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::create('branch_user', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->primary(['branch_id', 'user_id']);
            $table->index(['user_id', 'is_default']);
        });

        foreach (DB::table('users')->select('id')->cursor() as $user) {
            DB::table('branch_user')->insert([
                'branch_id' => $mainBranchId,
                'user_id' => $user->id,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('branch_book_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_on_hand')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'book_id']);
        });

        Schema::create('user_book_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'user_id', 'book_id']);
        });

        $this->addBranchColumn('students');
        $this->addBranchColumn('student_registrations');
        $this->addBranchColumn('class_book_lists');
        $this->addBranchColumn('supplier_purchases');
        $this->addBranchColumn('supplier_returns');
        $this->addBranchColumn('supplier_payments');
        $this->addBranchColumn('invoices');
        $this->addBranchColumn('payments');
        $this->addBranchColumn('payment_reversals');
        $this->addBranchColumn('deferred_payment_approvals');
        $this->addBranchColumn('book_distributions');
        $this->addBranchColumn('book_returns');
        $this->addBranchColumn('stock_movements');
        $this->addBranchColumn('stock_adjustments');
        $this->addBranchColumn('credit_notes');
        $this->addBranchColumn('refunds');

        Schema::table('class_book_lists', function (Blueprint $table): void {
            // MySQL uses the former composite unique key for the
            // academic_year_id foreign key. Keep a dedicated index before
            // replacing that key with the branch-aware constraint.
            $table->index('academic_year_id');
            $table->dropUnique('book_list_scope_name_unique');
            $table->unique(['branch_id', 'academic_year_id', 'grade_id', 'section_id', 'name'], 'branch_book_list_scope_name_unique');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('holder_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        $branchTables = [
            'students', 'student_registrations', 'class_book_lists', 'supplier_purchases',
            'supplier_returns', 'supplier_payments', 'invoices', 'payments', 'payment_reversals',
            'deferred_payment_approvals', 'book_distributions', 'book_returns', 'stock_movements',
            'stock_adjustments', 'credit_notes', 'refunds',
        ];
        foreach ($branchTables as $table) {
            DB::table($table)->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
        });
        DB::table('audit_logs')->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);

        foreach (DB::table('books')->select('id', 'stock_on_hand', 'reserved_quantity')->cursor() as $book) {
            DB::table('branch_book_stocks')->insert([
                'branch_id' => $mainBranchId,
                'book_id' => $book->id,
                'quantity_on_hand' => $book->stock_on_hand,
                'reserved_quantity' => $book->reserved_quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('book_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('transfer_number')->unique();
            $table->string('transfer_type')->index();
            $table->foreignId('source_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('destination_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('destination_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->date('transferred_on');
            $table->string('status')->default('completed')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['source_branch_id', 'transferred_on']);
            $table->index(['destination_branch_id', 'transferred_on']);
        });

        Schema::create('book_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['book_transfer_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_transfer_items');
        Schema::dropIfExists('book_transfers');
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::table('class_book_lists', function (Blueprint $table): void {
            $table->dropUnique('branch_book_list_scope_name_unique');
            $table->unique(['academic_year_id', 'grade_id', 'section_id', 'name'], 'book_list_scope_name_unique');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('holder_user_id');
        });
        foreach ([
            'refunds', 'credit_notes', 'stock_adjustments', 'stock_movements', 'book_returns',
            'book_distributions', 'deferred_payment_approvals', 'payment_reversals', 'payments',
            'invoices', 'supplier_payments', 'supplier_returns', 'supplier_purchases',
            'class_book_lists', 'student_registrations', 'students',
        ] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('branch_id');
            });
        }
        Schema::dropIfExists('user_book_stocks');
        Schema::dropIfExists('branch_book_stocks');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('branches');
    }

    private function addBranchColumn(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
        });
    }
};
