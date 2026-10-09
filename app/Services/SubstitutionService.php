<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TeacherAttendance;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use Carbon\Carbon;

final class SubstitutionService
{
    public function __construct(private readonly WorkloadService $workload, private readonly AuditService $audit) {}

    public function createForAbsence(TeacherAttendance $attendance): void
    {
        if (! $attendance->isAbsent()) {
            return;
        }

        $date = Carbon::parse($attendance->attendance_date);
        $entries = TimetableEntry::query()
            ->where('teacher_id', $attendance->teacher_id)
            ->where('day_of_week', strtolower($date->englishDayOfWeek))
            ->get();

        foreach ($entries as $entry) {
            $recommendation = $this->recommend($entry, $date, $attendance->teacher_id);
            $substitution = TeacherSubstitution::updateOrCreate(
                ['substitution_date' => $date->toDateString(), 'timetable_entry_id' => $entry->id],
                [
                    'absent_teacher_id' => $attendance->teacher_id,
                    'substitute_teacher_id' => $recommendation['teacher']?->id,
                    'status' => $recommendation['teacher'] ? 'recommended' : 'pending',
                    'recommendation_details' => $recommendation['details'],
                ],
            );
            $this->audit->log('teacher_substitution.recommended', $substitution, [], $substitution->recommendation_details ?? []);
        }
    }

    /** @return array{teacher:?Teacher,details:array<string,mixed>} */
    public function recommend(TimetableEntry $entry, Carbon $date, ?int $absentTeacherId = null): array
    {
        $candidates = Teacher::query()->where('is_active', true)->when($absentTeacherId, fn ($query) => $query->whereKeyNot($absentTeacherId))->get()
            ->filter(fn (Teacher $teacher) => $this->workload->isAvailable($teacher, $date, $entry->period_number, $entry->academic_year_id))
            ->map(function (Teacher $teacher) use ($entry, $date): array {
                $summary = $this->workload->summary($teacher, $entry->academic_year_id, $date);
                $compatible = TeacherAssignment::query()
                    ->where('teacher_id', $teacher->id)
                    ->where('subject_id', $entry->subject_id)
                    ->where('academic_year_id', $entry->academic_year_id)
                    ->where('is_active', true)
                    ->exists();
                $adjacent = TimetableEntry::query()
                    ->where('teacher_id', $teacher->id)
                    ->where('academic_year_id', $entry->academic_year_id)
                    ->where('day_of_week', $entry->day_of_week)
                    ->whereIn('period_number', [$entry->period_number - 1, $entry->period_number + 1])
                    ->exists();
                $score = ($summary['substitution_count'] * 100) + ($summary['weekly_periods'] * 10) + ($summary['daily_periods'] * 5) + ($adjacent ? 8 : 0) + ($compatible ? 0 : 20);

                return compact('teacher', 'summary', 'compatible', 'adjacent', 'score');
            })
            ->sortBy('score')
            ->values();

        $best = $candidates->first();
        if (! $best) {
            return ['teacher' => null, 'details' => ['reason' => 'No available teacher meets the timetable and absence rules.', 'candidates' => []]];
        }

        /** @var Teacher $teacher */
        $teacher = $best['teacher'];
        $reasons = [
            "Available for period {$entry->period_number}",
            "{$best['summary']['substitution_count']} substitution(s) this week",
            "{$best['summary']['weekly_periods']} scheduled teaching period(s) this week",
        ];
        if ($best['compatible']) {
            $reasons[] = 'Has an active assignment for this subject';
        }
        if (! $best['adjacent']) {
            $reasons[] = 'No adjacent scheduled lesson';
        }

        return [
            'teacher' => $teacher,
            'details' => [
                'reason' => 'Selected with the lowest fairness score among available teachers.',
                'reasons' => $reasons,
                'score' => $best['score'],
                'candidates' => $candidates->take(5)->map(fn (array $candidate) => [
                    'teacher_id' => $candidate['teacher']->id,
                    'teacher' => $candidate['teacher']->full_name,
                    'score' => $candidate['score'],
                    'weekly_periods' => $candidate['summary']['weekly_periods'],
                    'substitution_count' => $candidate['summary']['substitution_count'],
                ])->all(),
            ],
        ];
    }
}
