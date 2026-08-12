<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
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
            Permission::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        $roles = [
            'super-administrator' => [
                'label' => 'Super Administrator',
                'description' => 'Unrestricted system administrator',
                'permissions' => array_keys($permissions),
            ],
            'employee' => [
                'label' => 'Employee',
                'description' => 'General school employee',
                'permissions' => ['dashboard.view', 'students.view', 'books.view', 'suppliers.view', 'reports.view'],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['label' => $data['label'], 'description' => $data['description']],
            );
            $role->permissions()->sync(Permission::whereIn('name', $data['permissions'])->pluck('id'));
        }

        $administrator = User::firstOrCreate(
            ['email' => env('SBM_ADMIN_EMAIL', 'admin@school.test')],
            [
                'name' => env('SBM_ADMIN_NAME', 'System Administrator'),
                'password' => Hash::make(env('SBM_ADMIN_PASSWORD', 'Password123@')),
                'is_active' => true,
            ],
        );
        $administrator->roles()->sync([Role::where('name', 'super-administrator')->value('id')]);
        if (! User::query()->where('is_controller_administrator', true)->exists()) {
            $administrator->update(['is_controller_administrator' => true, 'has_all_branch_access' => true]);
        }
        $mainBranch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main Branch', 'is_active' => true]);
        $administrator->branches()->syncWithoutDetaching([$mainBranch->id => ['is_default' => true]]);

        foreach ([
            ['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open'],
            ['name' => '2027-2028', 'starts_on' => '2027-06-04', 'ends_on' => '2028-06-04', 'status' => 'draft'],
        ] as $year) {
            AcademicYear::firstOrCreate(
                ['name' => $year['name']],
                [...$year, 'created_by' => $administrator->id],
            );
        }

        $preschool = SchoolLevel::firstOrCreate(['name' => 'Preschool'], ['sort_order' => 1]);
        $school = SchoolLevel::firstOrCreate(['name' => 'School'], ['sort_order' => 2]);

        $gradeNames = ['KG1', 'KG2', 'GR1', 'GR2', 'GR3', 'GR4', 'GR5', 'GR6', 'GR7', 'GR8', 'GR9', 'GR10', 'GR11', 'GR12'];
        foreach ($gradeNames as $index => $name) {
            $grade = Grade::firstOrCreate(
                ['school_level_id' => $index < 2 ? $preschool->id : $school->id, 'name' => $name],
                ['sort_order' => $index + 1],
            );

            foreach (['A', 'B', 'C', 'D'] as $sectionName) {
                Section::firstOrCreate(['grade_id' => $grade->id, 'name' => $sectionName]);
            }
        }
    }
}
