<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_contents', function (Blueprint $table): void {
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('due_on');
            $table->boolean('disable_response')->default(true)->after('estimated_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('course_contents', function (Blueprint $table): void {
            $table->dropColumn(['estimated_minutes', 'disable_response']);
        });
    }
};
