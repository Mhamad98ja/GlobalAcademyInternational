<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('teacher_assignment_id')
                ->nullable()
                ->after('section_id')
                ->constrained('teacher_assignments')
                ->nullOnDelete();
            $table->index(['teacher_assignment_id', 'status'], 'assessment_assignment_status_index');
        });

        // Existing installations may have assessments created before the
        // teaching-assignment relationship existed. Link only unambiguous
        // records; ambiguous legacy records remain available to administrators
        // for manual correction.
        DB::table('assessments')->whereNull('teacher_assignment_id')->orderBy('id')->get()->each(function (object $assessment): void {
            $exact = DB::table('teacher_assignments')
                ->where('academic_year_id', $assessment->academic_year_id)
                ->where('subject_id', $assessment->subject_id)
                ->where('grade_id', $assessment->grade_id)
                ->when($assessment->section_id === null, fn ($query) => $query->whereNull('section_id'))
                ->when($assessment->section_id !== null, fn ($query) => $query->where('section_id', $assessment->section_id))
                ->pluck('id');

            if ($exact->count() === 1) {
                DB::table('assessments')->where('id', $assessment->id)->update(['teacher_assignment_id' => $exact->first()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['teacher_assignment_id']);
            $table->dropIndex('assessment_assignment_status_index');
            $table->dropColumn('teacher_assignment_id');
        });
    }
};
