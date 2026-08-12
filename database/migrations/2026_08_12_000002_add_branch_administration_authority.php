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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_controller_administrator')->default(false)->after('is_active')->index();
            $table->boolean('has_all_branch_access')->default(false)->after('is_controller_administrator')->index();
        });

        Schema::table('branch_user', function (Blueprint $table): void {
            $table->boolean('is_branch_administrator')->default(false)->after('is_default');
        });

        $controllerId = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('users.is_active', true)
            ->where('roles.name', 'super-administrator')
            ->orderBy('users.id')
            ->value('users.id');

        if ($controllerId) {
            DB::table('users')->where('id', $controllerId)->update([
                'is_controller_administrator' => true,
                'has_all_branch_access' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('branch_user', function (Blueprint $table): void {
            $table->dropColumn('is_branch_administrator');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_controller_administrator']);
            $table->dropIndex(['has_all_branch_access']);
            $table->dropColumn(['is_controller_administrator', 'has_all_branch_access']);
        });
    }
};
