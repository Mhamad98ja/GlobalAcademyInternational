<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deferred_payment_approvals', function (Blueprint $table): void {
            $table->text('decision_notes')->nullable()->after('notes');
        });

        $employeeRoleId = DB::table('roles')->where('name', 'employee')->value('id');
        $deferredCreatePermissionId = DB::table('permissions')->where('name', 'deferred.create')->value('id');
        if ($employeeRoleId !== null && $deferredCreatePermissionId !== null) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $employeeRoleId,
                'permission_id' => $deferredCreatePermissionId,
            ]);
        }
    }

    public function down(): void
    {
        $employeeRoleId = DB::table('roles')->where('name', 'employee')->value('id');
        $deferredCreatePermissionId = DB::table('permissions')->where('name', 'deferred.create')->value('id');
        if ($employeeRoleId !== null && $deferredCreatePermissionId !== null) {
            DB::table('permission_role')
                ->where('role_id', $employeeRoleId)
                ->where('permission_id', $deferredCreatePermissionId)
                ->delete();
        }

        Schema::table('deferred_payment_approvals', function (Blueprint $table): void {
            $table->dropColumn('decision_notes');
        });
    }
};
