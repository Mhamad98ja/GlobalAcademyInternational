<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_announcements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience', 30)->default('all');
            $table->string('title', 180);
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'published_at']);
            $table->index(['academic_year_id', 'audience']);
        });

        Schema::create('course_contents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('content')->nullable();
            $table->string('content_type', 30)->default('lesson');
            $table->string('resource_url', 2000)->nullable();
            $table->timestamp('available_from')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['teacher_assignment_id', 'published_at']);
            $table->index(['branch_id', 'due_on']);
        });

        Schema::create('school_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience', 30)->default('all');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'starts_at']);
        });

        Schema::create('student_performance_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->date('observed_on');
            $table->text('note');
            $table->string('status', 20)->default('published');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_registration_id', 'observed_on'], 'sp_eval_registration_observed_idx');
            $table->index(['teacher_assignment_id', 'status'], 'sp_eval_assignment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_performance_evaluations');
        Schema::dropIfExists('school_events');
        Schema::dropIfExists('course_contents');
        Schema::dropIfExists('school_announcements');
    }
};
