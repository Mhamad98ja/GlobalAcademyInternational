<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'employee')->value('id');
        if (! $roleId) {
            return;
        }

        $permissions = DB::table('permissions')->whereIn('name', ['collaboration.view', 'collaboration.manage'])->pluck('id', 'name');
        foreach ($permissions as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'employee')->value('id');
        $permissionIds = DB::table('permissions')->whereIn('name', ['collaboration.view', 'collaboration.manage'])->pluck('id');
        if ($roleId && $permissionIds->isNotEmpty()) {
            DB::table('permission_role')->where('role_id', $roleId)->whereIn('permission_id', $permissionIds)->delete();
        }
    }
};
