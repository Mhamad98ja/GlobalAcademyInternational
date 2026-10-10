<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'teacher')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'grades.enter')->value('id');
        if ($roleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'teacher')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'grades.enter')->value('id');
        if ($roleId && $permissionId) {
            DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
        }
    }
};
