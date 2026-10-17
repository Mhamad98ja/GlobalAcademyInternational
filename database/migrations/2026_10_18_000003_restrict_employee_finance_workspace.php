<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'employee')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'operations.view')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
    }
};
