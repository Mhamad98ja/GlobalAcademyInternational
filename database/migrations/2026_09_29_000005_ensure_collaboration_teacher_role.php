<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $teacherId = DB::table('roles')->where('name', 'teacher')->value('id');

        if (! $teacherId) {
            $teacherId = DB::table('roles')->insertGetId([
                'name' => 'teacher',
                'label' => 'Teacher',
                'description' => 'Assigned-course grading, course content, and student performance follow-up.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionNames = [
            'dashboard.view',
            'students.view',
            'grades.enter',
            'timetable.view',
            'collaboration.view',
            'collaboration.manage',
            'performance.view',
            'performance.manage',
        ];

        foreach (DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id') as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $teacherId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // The shared teacher role and permission links may be in use by existing users.
    }
};
