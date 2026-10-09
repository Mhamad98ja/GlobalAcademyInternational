<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_reference_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 60);
            $table->string('category', 120)->nullable();
            $table->string('code', 120)->nullable();
            $table->string('value', 255);
            $table->string('value_en', 255)->nullable();
            $table->string('value_ar', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['reference', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_reference_entries');
    }
};
