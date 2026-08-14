<?php

declare(strict_types=1);

use App\Models\Grade;
use App\Models\SchoolLevel;
use App\Models\Section;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $preschool = SchoolLevel::updateOrCreate(['name' => 'Preschool'], ['sort_order' => 1]);
        $school = SchoolLevel::updateOrCreate(['name' => 'School'], ['sort_order' => 2]);

        $structure = [
            [$preschool, 'Preschool', 1, ['A'], null],
            [$preschool, 'KG1', 2, ['A', 'B'], null],
            [$preschool, 'KG2', 3, ['A', 'B', 'C'], null],
            [$school, 'Grade 1', 4, ['A', 'B', 'C'], 'GR1'],
            [$school, 'Grade 2', 5, ['A', 'B', 'C'], 'GR2'],
            [$school, 'Grade 3', 6, ['A', 'B', 'C'], 'GR3'],
            [$school, 'Grade 4', 7, ['B', 'C'], 'GR4'],
            [$school, 'Grade 5', 8, ['A', 'B'], 'GR5'],
            [$school, 'Grade 6', 9, ['A', 'B'], 'GR6'],
            [$school, 'Grade 7', 10, ['A', 'B'], 'GR7'],
            [$school, 'Grade 8', 11, ['A', 'B', 'C'], 'GR8'],
            [$school, 'Grade 9', 12, ['A', 'B', 'C'], 'GR9'],
            [$school, 'Grade 10', 13, ['A', 'B', 'C', 'D'], 'GR10'],
            [$school, 'Grade 11', 14, ['A', 'B', 'C'], 'GR11'],
            [$school, 'Grade 12', 15, ['A', 'B', 'C', 'D'], 'GR12'],
        ];

        foreach ($structure as [$level, $name, $sortOrder, $sectionNames, $legacyName]) {
            $grade = Grade::query()
                ->where('school_level_id', $level->id)
                ->where('name', $name)
                ->first();

            if ($grade === null && $legacyName !== null) {
                $grade = Grade::query()
                    ->where('school_level_id', $level->id)
                    ->where('name', $legacyName)
                    ->first();
            }

            $grade ??= new Grade(['school_level_id' => $level->id]);
            $grade->fill(['name' => $name, 'sort_order' => $sortOrder])->save();

            foreach ($sectionNames as $sectionName) {
                Section::firstOrCreate(['grade_id' => $grade->id, 'name' => $sectionName]);
            }
        }
    }

    public function down(): void
    {
        // This migration normalizes existing academic records and is intentionally irreversible.
    }
};
