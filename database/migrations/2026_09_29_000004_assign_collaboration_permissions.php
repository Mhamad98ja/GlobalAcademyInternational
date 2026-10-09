<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'collaboration.view' => 'View collaboration, notices, and calendar',
            'collaboration.manage' => 'Publish school notices, events, and course content',
            'performance.view' => 'View student performance evaluations',
            'performance.manage' => 'Record student performance evaluations',
        ];
        foreach ($permissions as $name => $label) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'created_at' => now(), 'updated_at' => now()]);
        }

        if (! DB::table('roles')->where('name', 'teacher')->exists()) {
            DB::table('roles')->insert([
                'name' => 'teacher',
                'label' => 'Teacher',
                'description' => 'Assigned-course grading, course content, and student performance follow-up.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolePermissions = [
            'employee' => ['collaboration.view', 'performance.view'],
            'teacher' => ['dashboard.view', 'students.view', 'grades.enter', 'timetable.view', 'collaboration.view', 'collaboration.manage', 'performance.view', 'performance.manage'],
        ];
        foreach ($rolePermissions as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach (DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id') as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // Collaboration permission records remain so audit records keep their meaning.
    }
};
