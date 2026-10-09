<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['messages.view' => 'View internal school messages', 'messages.send' => 'Send internal school messages'] as $name => $label) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (['employee', 'teacher'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach (DB::table('permissions')->whereIn('name', ['messages.view', 'messages.send'])->pluck('id') as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // Keep shared permissions and role grants for existing audit continuity.
    }
};
