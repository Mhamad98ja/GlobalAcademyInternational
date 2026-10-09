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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('teacher_id')->nullable()->after('whatsapp_number')->constrained()->nullOnDelete();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type')->default('semester');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('draft')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'academic_year_id', 'name']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('required_periods_per_week')->default(0);
            $table->unsignedSmallInteger('weekly_period_limit')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'academic_year_id', 'teacher_id', 'subject_id', 'grade_id', 'section_id'], 'teacher_assignment_scope_unique');
        });

        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('test');
            $table->decimal('maximum_score', 8, 2)->default(100);
            $table->date('assessment_date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['academic_year_id', 'subject_id', 'grade_id', 'section_id'], 'assessment_scope_index');
        });

        Schema::create('student_attendances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->unsignedSmallInteger('period_number')->default(0);
            $table->string('status')->default('present')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'attendance_date', 'period_number'], 'student_attendance_once_per_period');
            $table->index(['academic_year_id', 'grade_id', 'section_id', 'attendance_date'], 'student_attendance_rollup_index');
        });

        Schema::create('teacher_attendances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->time('checked_in_at')->nullable();
            $table->time('checked_out_at')->nullable();
            $table->string('status')->default('present')->index();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'teacher_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });

        Schema::create('action_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('kind')->default('discipline');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('action_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('action_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['action_category_id', 'name']);
        });

        Schema::create('student_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_registration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('action_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('action_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->string('location')->nullable();
            $table->text('description');
            $table->text('witnesses')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('consequence')->nullable();
            $table->text('follow_up')->nullable();
            $table->boolean('parent_notified')->default(false);
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('open')->index();
            $table->foreignId('reported_by_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'incident_date']);
            $table->index(['academic_year_id', 'action_type_id', 'status']);
        });

        Schema::create('medical_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->date('recorded_on');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('restrictions')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'recorded_on']);
            $table->index(['teacher_id', 'recorded_on']);
        });

        Schema::create('exam_absences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->date('absence_date');
            $table->string('reason')->nullable();
            $table->boolean('is_excused')->default(false);
            $table->boolean('makeup_required')->default(false);
            $table->date('makeup_exam_date')->nullable();
            $table->string('medical_attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('recorded')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'assessment_id']);
            $table->index(['academic_year_id', 'subject_id', 'absence_date']);
        });

        Schema::create('grade_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_locked')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'assessment_id']);
            $table->index(['teacher_assignment_id', 'status']);
        });

        Schema::create('grade_change_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('timetable_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('period_number');
            $table->string('label');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('kind')->default('lesson');
            $table->timestamps();
            $table->unique(['branch_id', 'academic_year_id', 'period_number'], 'timetable_period_scope_unique');
        });

        Schema::create('timetable_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('day_of_week', 12);
            $table->unsignedSmallInteger('period_number');
            $table->string('room')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'academic_year_id', 'grade_id', 'section_id', 'day_of_week', 'period_number'], 'timetable_section_slot_unique');
            $table->index(['academic_year_id', 'teacher_id', 'day_of_week', 'period_number'], 'timetable_teacher_slot_index');
        });

        Schema::create('teacher_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('substitution_date');
            $table->foreignId('timetable_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('absent_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->foreignId('substitute_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->json('recommendation_details')->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['substitution_date', 'timetable_entry_id'], 'teacher_substitution_slot_unique');
            $table->index(['substitution_date', 'substitute_teacher_id', 'status'], 'teacher_substitution_lookup_index');
        });

        $permissions = [
            'attendance.student.manage' => 'Manage student attendance',
            'attendance.teacher.manage' => 'Manage teacher attendance',
            'discipline.view' => 'View student incidents',
            'discipline.create' => 'Create student incidents',
            'discipline.edit' => 'Edit student incidents',
            'medical.view' => 'View medical archives',
            'medical.manage' => 'Manage medical archives',
            'timetable.view' => 'View timetables',
            'timetable.manage' => 'Manage timetables',
            'substitutions.manage' => 'Manage teacher substitutions',
            'grades.enter' => 'Enter grades for assigned courses',
            'grades.publish' => 'Publish and lock grades',
            'academic_year.manage' => 'Configure academic terms and assessments',
            'reports.export' => 'Export school reports',
        ];

        foreach ($permissions as $name => $label) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'created_at' => now(), 'updated_at' => now()]);
        }

        $administratorRoleId = DB::table('roles')->where('name', 'super-administrator')->value('id');
        if ($administratorRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('name', array_keys($permissions))->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $administratorRoleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_substitutions');
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('timetable_periods');
        Schema::dropIfExists('grade_change_audits');
        Schema::dropIfExists('grade_entries');
        Schema::dropIfExists('exam_absences');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('student_incidents');
        Schema::dropIfExists('action_types');
        Schema::dropIfExists('action_categories');
        Schema::dropIfExists('teacher_attendances');
        Schema::dropIfExists('student_attendances');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('subjects');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('teacher_id');
        });
    }
};
