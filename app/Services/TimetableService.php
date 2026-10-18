<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use Illuminate\Validation\ValidationException;

final class TimetableService
{
    /** @param array{day_of_week:string,period_number:int,section_id?:int|null,room?:string|null} $slot */
    public function create(TeacherAssignment $assignment, array $slot): TimetableEntry
    {
        $attributes = $this->attributes($assignment, $slot);
        $this->ensureAvailable($attributes);

        return TimetableEntry::create($attributes);
    }

    /** @param array{day_of_week:string,period_number:int,section_id?:int|null,room?:string|null} $slot */
    public function update(TimetableEntry $entry, TeacherAssignment $assignment, array $slot): TimetableEntry
    {
        $attributes = $this->attributes($assignment, $slot);
        $this->ensureAvailable($attributes, $entry);
        $entry->update($attributes);

        return $entry->refresh();
    }

    /** @param array{day_of_week:string,period_number:int,section_id?:int|null,room?:string|null} $slot
     * @return array<string, mixed>
     */
    private function attributes(TeacherAssignment $assignment, array $slot): array
    {
        return [
            'academic_year_id' => $assignment->academic_year_id,
            'teacher_assignment_id' => $assignment->id,
            'teacher_id' => $assignment->teacher_id,
            'subject_id' => $assignment->subject_id,
            'grade_id' => $assignment->grade_id,
            'section_id' => array_key_exists('section_id', $slot) && $slot['section_id'] !== null
                ? (int) $slot['section_id']
                : $assignment->section_id,
            'day_of_week' => strtolower($slot['day_of_week']),
            'period_number' => (int) $slot['period_number'],
            'is_parallel' => false,
            'room' => blank($slot['room'] ?? null) ? null : $slot['room'],
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function ensureAvailable(array $attributes, ?TimetableEntry $ignore = null): void
    {
        $withoutCurrent = fn ($query) => $ignore ? $query->where('id', '!=', $ignore->id) : $query;
        $slot = fn ($query) => $query
            ->where('academic_year_id', $attributes['academic_year_id'])
            ->where('day_of_week', $attributes['day_of_week'])
            ->where('period_number', $attributes['period_number']);

        $sectionConflict = $withoutCurrent($slot(TimetableEntry::query())
            ->where('grade_id', $attributes['grade_id'])
            ->where('section_id', $attributes['section_id']))
            ->exists();
        if ($sectionConflict) {
            throw ValidationException::withMessages(['period_number' => 'This class section already has a lesson in that period. Choose an empty period or move the existing lesson first.']);
        }

        $teacherConflict = $withoutCurrent($slot(TimetableEntry::query())->where('teacher_id', $attributes['teacher_id']))->exists();
        if ($teacherConflict) {
            throw ValidationException::withMessages(['teacher_assignment_id' => 'This teacher is already assigned to another class in that timetable slot.']);
        }

        if ($attributes['room'] && $withoutCurrent($slot(TimetableEntry::query())->where('room', $attributes['room']))->exists()) {
            throw ValidationException::withMessages(['room' => 'This room is already booked in that timetable slot.']);
        }

        $assignment = TeacherAssignment::findOrFail($attributes['teacher_assignment_id']);
        $scheduledForAssignment = $withoutCurrent(TimetableEntry::query()
            ->where('teacher_assignment_id', $assignment->id)
            ->where('section_id', $attributes['section_id']))
            ->count();
        if ($assignment->required_periods_per_week > 0 && $scheduledForAssignment >= $assignment->required_periods_per_week) {
            throw ValidationException::withMessages(['teacher_assignment_id' => 'The required weekly periods for this assignment are already scheduled.']);
        }

        $teacherWeeklyLimit = TeacherAssignment::query()
            ->where('teacher_id', $attributes['teacher_id'])
            ->where('academic_year_id', $attributes['academic_year_id'])
            ->where('is_active', true)
            ->whereNotNull('weekly_period_limit')
            ->min('weekly_period_limit');
        if ($teacherWeeklyLimit !== null) {
            $teacherWeeklyPeriods = $withoutCurrent(TimetableEntry::query()
                ->where('academic_year_id', $attributes['academic_year_id'])
                ->where('teacher_id', $attributes['teacher_id']))
                ->count();
            if ($teacherWeeklyPeriods >= (int) $teacherWeeklyLimit) {
                throw ValidationException::withMessages(['teacher_assignment_id' => 'Scheduling this lesson would exceed the teacher weekly workload limit.']);
            }
        }
    }
}
