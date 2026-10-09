<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'reports.export' => 'Export school reports',
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
        ];
        foreach ($permissions as $name => $label) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'created_at' => now(), 'updated_at' => now()]);
        }

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys($permissions))->pluck('id');
        foreach (['super-administrator', 'employee'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            $ids = $roleName === 'employee'
                ? DB::table('permissions')->whereIn('name', ['attendance.student.manage', 'attendance.teacher.manage', 'discipline.view', 'discipline.create', 'discipline.edit', 'grades.enter', 'timetable.view'])->pluck('id')
                : $permissionIds;
            foreach ($ids as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // Permission records are intentionally retained so historical role and audit records remain intelligible.
    }
};
