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
            $table->foreignId('student_id')->nullable()->unique()->after('teacher_id')->constrained('students')->nullOnDelete();
        });

        Schema::table('assessments', function (Blueprint $table): void {
            $table->decimal('weight', 6, 3)->default(1)->after('maximum_score');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        DB::table('roles')->updateOrInsert(
            ['name' => 'student'],
            ['label' => 'Student', 'description' => 'Student portal account with access only to the student portal.', 'created_at' => now(), 'updated_at' => now()],
        );

        $manageContent = DB::table('permissions')->where('name', 'collaboration.manage')->value('id');
        $employeeRole = DB::table('roles')->where('name', 'employee')->value('id');
        if ($manageContent && $employeeRole) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $employeeRole, 'permission_id' => $manageContent]);
        }
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'weight']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['student_id']);
            $table->dropUnique(['student_id']);
            $table->dropColumn('student_id');
        });
        DB::table('roles')->where('name', 'student')->delete();
    }
};
