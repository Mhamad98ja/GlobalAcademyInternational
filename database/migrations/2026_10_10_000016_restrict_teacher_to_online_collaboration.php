<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'teacher')->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['collaboration.view', 'collaboration.manage', 'performance.view', 'performance.manage', 'messages.view', 'messages.send'])
            ->pluck('id');

        DB::table('permission_role')->where('role_id', $roleId)->delete();
        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Teacher access is intentionally managed by the seeder's role definition.
    }
};
