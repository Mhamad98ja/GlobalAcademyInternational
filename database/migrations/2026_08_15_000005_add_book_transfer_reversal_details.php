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
            $table->text('reversal_notes')->nullable()->after('notes');
            $table->foreignId('reversed_by')->nullable()->after('completed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');
        });
    }

    public function down(): void
    {
        Schema::table('book_transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['reversal_notes', 'reversed_at']);
        });
    }
};
