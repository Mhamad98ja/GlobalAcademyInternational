<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_modification_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();

            $table->index(['assessment_id', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_modification_requests');
    }
};
