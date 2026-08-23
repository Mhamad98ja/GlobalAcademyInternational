<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $employeeRoleId = DB::table('roles')->where('name', 'employee')->value('id');
        if ($employeeRoleId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['inventory.view', 'inventory.transfer'])
            ->pluck('id');
        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')
                ->where('role_id', $employeeRoleId)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }
    }

    public function down(): void
    {
        $employeeRoleId = DB::table('roles')->where('name', 'employee')->value('id');
        if ($employeeRoleId === null) {
            return;
        }

        DB::table('permissions')
            ->whereIn('name', ['inventory.view', 'inventory.transfer'])
            ->pluck('id')
            ->each(fn ($permissionId) => DB::table('permission_role')->insertOrIgnore([
                'role_id' => $employeeRoleId,
                'permission_id' => (int) $permissionId,
            ]));
    }
};
