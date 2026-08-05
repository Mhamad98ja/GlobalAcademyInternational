<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registrations', function (Blueprint $table): void {
            $table->date('withdrawn_on')->nullable();
            $table->text('withdrawal_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_registrations', function (Blueprint $table): void {
            $table->dropColumn(['withdrawn_on', 'withdrawal_reason']);
        });
    }
};
