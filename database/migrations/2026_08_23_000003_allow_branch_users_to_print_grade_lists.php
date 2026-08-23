<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'class-lists.view'],
            ['label' => 'View and print grade book lists', 'updated_at' => now(), 'created_at' => now()],
        );

        $employeeRoleId = DB::table('roles')->where('name', 'employee')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'class-lists.view')->value('id');
        if ($employeeRoleId !== null && $permissionId !== null) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $employeeRoleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $employeeRoleId = DB::table('roles')->where('name', 'employee')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'class-lists.view')->value('id');
        if ($employeeRoleId !== null && $permissionId !== null) {
            DB::table('permission_role')
                ->where('role_id', $employeeRoleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }

        DB::table('permissions')->where('name', 'class-lists.view')->delete();
    }
};
