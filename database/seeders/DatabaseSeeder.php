<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = PermissionCatalog::all();

        foreach ($permissions as $name => $label) {
            Permission::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        $rolePermissions = PermissionCatalog::rolePermissions();
        $roles = [
            'super-administrator' => [
                'label' => 'Branch Administrator',
                'description' => 'Branch operations and user management within assigned branches; global configuration is reserved for the Global Super Administrator.',
                'permissions' => $rolePermissions['super-administrator'],
            ],
            'employee' => [
                'label' => 'Branch User',
                'description' => 'Daily student, inventory, transfer, and distribution work without financial access.',
                'permissions' => $rolePermissions['employee'],
            ],
            'teacher' => [
                'label' => 'Teacher',
                'description' => 'Online collaboration access for assigned subjects, agenda, messages, and performance follow-up.',
                'permissions' => $rolePermissions['teacher'],
            ],
            'student' => [
                'label' => 'Student',
                'description' => 'Access to the linked student portal and published course content.',
                'permissions' => $rolePermissions['student'],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
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

        $this->call(SchoolStructureSeeder::class);
    }
}
