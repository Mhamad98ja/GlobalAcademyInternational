<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'dashboard.view' => 'View dashboard',
            'students.view' => 'View students',
            'students.manage' => 'Manage students',
            'books.view' => 'View books',
            'books.manage' => 'Manage books',
            'class-lists.manage' => 'Manage book packages',
            'suppliers.view' => 'View suppliers',
            'suppliers.manage' => 'Manage suppliers',
            'purchases.manage' => 'Manage supplier purchases',
            'distributions.create' => 'Create distributions',
            'invoices.view' => 'View invoices',
            'invoices.cancel' => 'Cancel invoices',
            'payments.view' => 'View payments',
            'payments.create' => 'Record payments',
            'payments.reverse' => 'Reverse payments',
            'inventory.view' => 'View inventory',
            'inventory.adjust' => 'Adjust inventory',
            'inventory.transfer' => 'Transfer books between branches and users',
            'returns.manage' => 'Manage returns',
            'deferred.view' => 'View deferred approvals',
            'deferred.create' => 'Create deferred payments',
            'deferred.approve' => 'Approve deferred payments',
            'academic.manage' => 'Manage academic structure',
            'people.manage' => 'Manage people',
            'reports.view' => 'View reports',
            'admin.manage' => 'Manage administration',
        ];

        foreach ($permissions as $name => $label) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'updated_at' => now(), 'created_at' => now()]);
        }

        $superAdministratorId = $this->updateRole(
            'super-administrator',
            'Branch Super Administrator',
            'Full operational and financial administration within the branches assigned to the account.',
        );
        $employeeId = $this->updateRole(
            'employee',
            'Branch User',
            'Daily student, inventory, transfer, and distribution work without access to financial information.',
        );

        if ($superAdministratorId !== null) {
            $this->syncPermissions($superAdministratorId, array_keys($permissions));
        }
        if ($employeeId !== null) {
            $this->syncPermissions($employeeId, [
            'dashboard.view',
            'students.view',
            'students.manage',
            'books.view',
            'distributions.create',
            'inventory.view',
            'inventory.transfer',
            'people.manage',
            'reports.view',
            ]);
        }

        DB::table('users')
            ->where('is_controller_administrator', false)
            ->where('has_all_branch_access', true)
            ->update(['has_all_branch_access' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $superAdministratorId = DB::table('roles')->where('name', 'super-administrator')->value('id');
        $employeeId = DB::table('roles')->where('name', 'employee')->value('id');

        if ($superAdministratorId) {
            DB::table('roles')->where('id', $superAdministratorId)->update([
                'label' => 'Super Administrator',
                'description' => 'Unrestricted system administrator',
                'updated_at' => now(),
            ]);
        }

        if ($employeeId) {
            DB::table('roles')->where('id', $employeeId)->update([
                'label' => 'Employee',
                'description' => 'Daily student, book package, distribution, payment, and reporting operations.',
                'updated_at' => now(),
            ]);
            $this->syncPermissions($employeeId, [
                'dashboard.view',
                'students.view',
                'students.manage',
                'books.view',
                'class-lists.manage',
                'distributions.create',
                'invoices.view',
                'payments.view',
                'payments.create',
                'people.manage',
                'reports.view',
            ]);
        }
    }

    private function updateRole(string $name, string $label, string $description): ?int
    {
        $roleId = DB::table('roles')->where('name', $name)->value('id');
        if ($roleId !== null) {
            DB::table('roles')->where('id', $roleId)->update([
                'label' => $label,
                'description' => $description,
                'updated_at' => now(),
            ]);
        }

        return $roleId === null ? null : (int) $roleId;
    }

    /** @param list<string> $permissionNames */
    private function syncPermissions(int $roleId, array $permissionNames): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');
        DB::table('permission_role')->where('role_id', $roleId)->delete();
        DB::table('permission_role')->insert($permissionIds->map(fn ($permissionId) => [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ])->all());
    }
};
