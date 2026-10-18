<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use Carbon\Carbon;

final class WorkloadService
{
    /** @return array{daily_periods:int,weekly_periods:int,total_weekly_periods:int,free_periods:int,substitution_count:int} */
    public function summary(Teacher $teacher, int $academicYearId, ?Carbon $date = null): array
    {
        $date ??= today();
        $day = strtolower($date->englishDayOfWeek);
        $weeklyPeriods = TimetableEntry::query()
            ->where('academic_year_id', $academicYearId)
            ->where('teacher_id', $teacher->id)
            ->count();
        $dailyPeriods = TimetableEntry::query()
            ->where('academic_year_id', $academicYearId)
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $day)
            ->count();
        $lessonPeriods = TimetablePeriod::query()
            ->where('academic_year_id', $academicYearId)
            ->where('kind', 'lesson')
            ->count();
        $substitutions = TeacherSubstitution::query()
            ->where('substitute_teacher_id', $teacher->id)
            ->whereIn('status', ['assigned', 'completed'])
            ->whereHas('entry', fn ($entries) => $entries->where('academic_year_id', $academicYearId))
            ->whereBetween('substitution_date', [$date->copy()->startOfWeek()->toDateString(), $date->copy()->endOfWeek()->toDateString()])
            ->count();

        return [
            'daily_periods' => $dailyPeriods,
            'weekly_periods' => $weeklyPeriods,
            'total_weekly_periods' => $weeklyPeriods + $substitutions,
            'free_periods' => max(0, $lessonPeriods - $dailyPeriods),
            'substitution_count' => $substitutions,
        ];
    }

    public function isAvailable(Teacher $teacher, Carbon $date, int $periodNumber, int $academicYearId): bool
    {
        $attendance = TeacherAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->whereDate('attendance_date', $date)
            ->first();
        if ($attendance?->isAbsent()) {
            return false;
        }

        $day = strtolower($date->englishDayOfWeek);
        if (TimetableEntry::query()
            ->where('academic_year_id', $academicYearId)
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $day)
            ->where('period_number', $periodNumber)
            ->exists()) {
            return false;
        }

        return ! TeacherSubstitution::query()
            ->where('substitution_date', $date->toDateString())
            ->where('substitute_teacher_id', $teacher->id)
            ->whereIn('status', ['assigned', 'completed'])
            ->whereHas('entry', fn ($entries) => $entries->where('period_number', $periodNumber))
            ->exists();
    }
}
