<?php

declare(strict_types=1);

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $name = 'students.search';
        DB::table('permissions')->updateOrInsert(
            ['name' => $name],
            ['label' => PermissionCatalog::all()[$name], 'updated_at' => now(), 'created_at' => now()],
        );

        foreach (['employee', 'teacher'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');
            if ($roleId && $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
    }
};
