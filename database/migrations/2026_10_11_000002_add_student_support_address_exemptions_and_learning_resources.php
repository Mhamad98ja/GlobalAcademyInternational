<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('area', 120)->nullable()->after('address');
            $table->string('street', 150)->nullable()->after('area');
            $table->string('area_number', 30)->nullable()->after('street');
            $table->string('street_number', 30)->nullable()->after('area_number');
            $table->string('building_name', 150)->nullable()->after('street_number');
            $table->string('building_number', 30)->nullable()->after('building_name');
            $table->string('apartment', 30)->nullable()->after('building_number');
        });

        Schema::table('responsible_persons', function (Blueprint $table): void {
            $table->string('area', 120)->nullable()->after('address');
            $table->string('street', 150)->nullable()->after('area');
            $table->string('area_number', 30)->nullable()->after('street');
            $table->string('street_number', 30)->nullable()->after('area_number');
            $table->string('building_name', 150)->nullable()->after('street_number');
            $table->string('building_number', 30)->nullable()->after('building_name');
            $table->string('apartment', 30)->nullable()->after('building_number');
        });

        Schema::create('student_support_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 100);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('assigned_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });

        Schema::create('student_subject_exemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'student_registration_id', 'subject_id'], 'student_subject_exemption_unique');
        });

        Schema::table('course_contents', function (Blueprint $table): void {
            $table->string('attachment_path', 2048)->nullable()->after('resource_url');
            $table->string('attachment_name', 255)->nullable()->after('attachment_path');
            $table->string('attachment_mime', 120)->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
        });

        Schema::table('school_events', function (Blueprint $table): void {
            $table->string('meeting_platform', 30)->nullable()->after('ends_at');
            $table->string('meeting_url', 2000)->nullable()->after('meeting_platform');
        });
    }

    public function down(): void
    {
        Schema::table('school_events', function (Blueprint $table): void {
            $table->dropColumn(['meeting_platform', 'meeting_url']);
        });
        Schema::table('course_contents', function (Blueprint $table): void {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
        });
        Schema::dropIfExists('student_subject_exemptions');
        Schema::dropIfExists('student_support_assignments');
        Schema::table('responsible_persons', function (Blueprint $table): void {
            $table->dropColumn(['area', 'street', 'area_number', 'street_number', 'building_name', 'building_number', 'apartment']);
        });
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['area', 'street', 'area_number', 'street_number', 'building_name', 'building_number', 'apartment']);
        });
    }
};
