<?php

declare(strict_types=1);

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PermissionCatalog::all() as $name => $label) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['label' => $label, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        foreach (PermissionCatalog::rolePermissions() as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
    }
};
