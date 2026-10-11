<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('timetable_entries', 'is_parallel')) {
            Schema::table('timetable_entries', function (Blueprint $table): void {
                $table->boolean('is_parallel')->default(false)->after('period_number');
            });
        }

        Schema::table('timetable_entries', function (Blueprint $table): void {
            // The original unique index was also the only index supporting the nullable branch FK.
            $table->index('branch_id', 'timetable_entries_branch_id_index');
            $table->dropUnique('timetable_section_slot_unique');
            $table->index(
                ['branch_id', 'academic_year_id', 'grade_id', 'section_id', 'day_of_week', 'period_number'],
                'timetable_section_slot_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->dropIndex('timetable_section_slot_lookup_index');
            $table->unique(
                ['branch_id', 'academic_year_id', 'grade_id', 'section_id', 'day_of_week', 'period_number'],
                'timetable_section_slot_unique',
            );
            $table->dropIndex('timetable_entries_branch_id_index');
            $table->dropColumn('is_parallel');
        });
    }
};
