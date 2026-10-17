<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fridayLessons = DB::table('timetable_entries')
            ->whereRaw('LOWER(day_of_week) = ?', ['friday'])
            ->get();
        if ($fridayLessons->isEmpty()) {
            return;
        }

        $sundayLessons = DB::table('timetable_entries')
            ->whereRaw('LOWER(day_of_week) = ?', ['sunday'])
            ->get();
        foreach ($fridayLessons as $friday) {
            $conflict = $sundayLessons->first(function ($sunday) use ($friday): bool {
                if ((int) $sunday->academic_year_id !== (int) $friday->academic_year_id
                    || (int) $sunday->branch_id !== (int) $friday->branch_id
                    || (int) $sunday->period_number !== (int) $friday->period_number) {
                    return false;
                }

                return ((int) $sunday->grade_id === (int) $friday->grade_id
                        && (int) $sunday->section_id === (int) $friday->section_id)
                    || (int) $sunday->teacher_id === (int) $friday->teacher_id
                    || ($friday->room !== null && $friday->room === $sunday->room);
            });

            if ($conflict) {
                throw new RuntimeException('Friday lesson #'.$friday->id.' conflicts with Sunday lesson #'.$conflict->id.'. Resolve the timetable before moving Friday lessons to Sunday.');
            }
        }

        DB::table('timetable_entries')
            ->whereRaw('LOWER(day_of_week) = ?', ['friday'])
            ->update(['day_of_week' => 'sunday']);
    }

    public function down(): void
    {
        // The destination may also contain lessons that were originally on Sunday.
        // Reversing this data change cannot be done safely without that history.
    }
};
