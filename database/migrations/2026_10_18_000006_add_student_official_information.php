<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('official_id_number', 100)->nullable()->after('registration_number');
            $table->string('nationality', 100)->nullable()->after('official_id_number');
            $table->string('place_of_birth', 150)->nullable()->after('nationality');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['official_id_number', 'nationality', 'place_of_birth']);
        });
    }
};
