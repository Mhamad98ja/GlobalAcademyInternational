<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropUnique('books_isbn_unique');
            $table->index('isbn');
            $table->string('media_format', 30)->nullable();
        });

        Schema::create('book_grade_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->string('audience', 20)->index();
            $table->timestamps();
            $table->unique(['book_id', 'grade_id', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_grade_usages');

        Schema::table('books', function (Blueprint $table): void {
            $table->dropIndex('books_isbn_index');
            $table->dropColumn('media_format');
            $table->unique('isbn');
        });
    }
};
