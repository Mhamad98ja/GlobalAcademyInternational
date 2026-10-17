<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Grade;
use App\Models\SchoolLevel;
use App\Models\Section;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchoolStructureSeeder extends Seeder
{
    public function run(): void
    {
        // Render runs DatabaseSeeder on every startup. Import the code snapshot
        // once only; later edits in References > Codes must not be overwritten.
        if (SystemSetting::query()->where('key', 'school_structure_imported_v1')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            foreach (config('school_structure.years') as $definition) {
                AcademicYear::query()->firstOrCreate(
                    ['name' => $definition['name']],
                    array_diff_key($definition, array_flip(['name'])),
                );
            }

            foreach (config('school_structure.levels') as $definition) {
                $level = SchoolLevel::query()->firstOrCreate(
                    ['name' => $definition['name']],
                    ['sort_order' => $definition['sort_order']],
                );
                foreach ($definition['grades'] as $gradeDefinition) {
                    $grade = Grade::query()->firstOrCreate(
                        ['school_level_id' => $level->id, 'name' => $gradeDefinition['name']],
                        ['sort_order' => $gradeDefinition['sort_order']],
                    );
                    foreach ($gradeDefinition['sections'] as $sectionName) {
                        Section::query()->firstOrCreate(['grade_id' => $grade->id, 'name' => $sectionName]);
                    }
                }
            }

            foreach (config('school_structure.terms') as $definition) {
                $branch = Branch::query()->where('code', $definition['branch'])->first();
                $year = AcademicYear::query()->where('name', $definition['year'])->first();
                if (! $branch || ! $year) {
                    throw new RuntimeException('A configured academic term has an unknown branch or year.');
                }
                AcademicTerm::withoutGlobalScopes()->firstOrCreate(
                    ['branch_id' => $branch->id, 'academic_year_id' => $year->id, 'name' => $definition['name']],
                    array_diff_key($definition, array_flip(['branch', 'year', 'name'])),
                );
            }

            SystemSetting::query()->create(['key' => 'school_structure_imported_v1', 'value' => now()->toDateTimeString()]);
        });
    }
}
