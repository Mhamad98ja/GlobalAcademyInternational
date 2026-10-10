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
            'class-lists.view' => 'View and print grade book lists',
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
            'academic.manage' => 'Manage academic structure',
            'people.manage' => 'Manage people',
            'reports.view' => 'View reports',
            'reports.export' => 'Export school reports',
            'attendance.student.manage' => 'Manage student attendance',
            'attendance.teacher.manage' => 'Manage teacher attendance',
            'discipline.view' => 'View student incidents',
            'discipline.create' => 'Create student incidents',
            'discipline.edit' => 'Edit student incidents',
            'medical.view' => 'View medical archives',
            'medical.manage' => 'Manage medical archives',
            'timetable.view' => 'View timetables',
            'timetable.manage' => 'Manage timetables',
            'substitutions.manage' => 'Manage teacher substitutions',
            'grades.enter' => 'Enter grades for assigned courses',
            'grades.publish' => 'Publish and lock grades',
            'academic_year.manage' => 'Configure academic terms and assessments',
            'collaboration.view' => 'View collaboration, notices, and calendar',
            'collaboration.manage' => 'Publish school notices, events, and course content',
            'performance.view' => 'View student performance evaluations',
            'performance.manage' => 'Record student performance evaluations',
            'messages.view' => 'View internal school messages',
            'messages.send' => 'Send internal school messages',
            'admin.manage' => 'Manage administration',
        ];

        foreach ($permissions as $name => $label) {
            Permission::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        $roles = [
            'super-administrator' => [
                'label' => 'Branch Super Administrator',
                'description' => 'Full operational and financial administration within assigned branches.',
                'permissions' => array_keys($permissions),
            ],
            'employee' => [
                'label' => 'Branch User',
                'description' => 'Daily student, inventory, transfer, and distribution work without financial access.',
                'permissions' => ['dashboard.view', 'students.view', 'students.manage', 'books.view', 'class-lists.view', 'distributions.create', 'people.manage', 'reports.view', 'attendance.student.manage', 'attendance.teacher.manage', 'discipline.view', 'discipline.create', 'discipline.edit', 'grades.enter', 'timetable.view', 'collaboration.view', 'collaboration.manage', 'performance.view', 'messages.view', 'messages.send'],
            ],
            'teacher' => [
                'label' => 'Teacher',
                'description' => 'Online collaboration access for assigned subjects, agenda, messages, and performance follow-up.',
                'permissions' => ['collaboration.view', 'collaboration.manage', 'performance.view', 'performance.manage', 'messages.view', 'messages.send', 'grades.enter'],
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

        foreach ([
            ['name' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'open'],
            ['name' => '2027-2028', 'starts_on' => '2027-06-04', 'ends_on' => '2028-06-04', 'status' => 'draft'],
        ] as $year) {
            AcademicYear::firstOrCreate(
                ['name' => $year['name']],
                [...$year, 'created_by' => $administrator->id],
            );
        }

        $preschool = SchoolLevel::updateOrCreate(['name' => 'Preschool'], ['sort_order' => 1]);
        $school = SchoolLevel::updateOrCreate(['name' => 'School'], ['sort_order' => 2]);

        $structure = [
            [$preschool, 'Preschool', 1, ['A']],
            [$preschool, 'KG1', 2, ['A', 'B']],
            [$preschool, 'KG2', 3, ['A', 'B', 'C']],
            [$school, 'Grade 1', 4, ['A', 'B', 'C']],
            [$school, 'Grade 2', 5, ['A', 'B', 'C']],
            [$school, 'Grade 3', 6, ['A', 'B', 'C']],
            [$school, 'Grade 4', 7, ['B', 'C']],
            [$school, 'Grade 5', 8, ['A', 'B']],
            [$school, 'Grade 6', 9, ['A', 'B']],
            [$school, 'Grade 7', 10, ['A', 'B']],
            [$school, 'Grade 8', 11, ['A', 'B', 'C']],
            [$school, 'Grade 9', 12, ['A', 'B', 'C']],
            [$school, 'Grade 10', 13, ['A', 'B', 'C', 'D']],
            [$school, 'Grade 11', 14, ['A', 'B', 'C']],
            [$school, 'Grade 12', 15, ['A', 'B', 'C', 'D']],
        ];

        foreach ($structure as [$level, $gradeName, $sortOrder, $sectionNames]) {
            $grade = Grade::updateOrCreate(
                ['school_level_id' => $level->id, 'name' => $gradeName],
                ['sort_order' => $sortOrder],
            );

            foreach ($sectionNames as $sectionName) {
                Section::firstOrCreate(['grade_id' => $grade->id, 'name' => $sectionName]);
            }
        }
    }
}
