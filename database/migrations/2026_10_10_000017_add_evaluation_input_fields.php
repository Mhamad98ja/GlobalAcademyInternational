<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->string('assessment_column', 80)->nullable()->after('name');
            $table->boolean('online_quiz')->default(false)->after('type');
            $table->boolean('per_question')->default(false)->after('online_quiz');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn(['assessment_column', 'online_quiz', 'per_question']);
        });
    }
};
