<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'dashboard.view' => 'View dashboard',
            'students.view' => 'View students',
            'students.manage' => 'Manage students',
            'books.view' => 'View books',
            'class-lists.manage' => 'Manage class book lists',
            'distributions.create' => 'Create distributions',
            'invoices.view' => 'View invoices',
            'payments.view' => 'View payments',
            'payments.create' => 'Record payments',
            'people.manage' => 'Manage people',
            'reports.view' => 'View reports',
        ];

        foreach ($permissions as $name => $label) {
            Permission::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        $employee = Role::firstOrCreate(
            ['name' => 'employee'],
            [
                'label' => 'Employee',
                'description' => 'Daily student, book package, distribution, payment, and reporting operations.',
            ],
        );
        $employee->permissions()->sync(Permission::whereIn('name', array_keys($permissions))->pluck('id'));
    }

    public function down(): void
    {
        $employee = Role::where('name', 'employee')->first();
        if ($employee) {
            $employee->permissions()->detach();
            $employee->delete();
        }
    }
};
