<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('timetable_entries')->update([
            'day_of_week' => DB::raw('LOWER(day_of_week)'),
        ]);
    }

    public function down(): void
    {
        // Day names are stored canonically in lowercase; no safe reverse exists.
    }
};
