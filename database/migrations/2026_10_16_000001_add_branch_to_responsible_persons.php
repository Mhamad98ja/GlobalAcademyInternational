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
        Schema::table('responsible_persons', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
        });

        $mainBranchId = DB::table('branches')->where('code', 'MAIN')->value('id');
        DB::table('responsible_persons')->select('id')->orderBy('id')->chunkById(100, function ($people) use ($mainBranchId): void {
            foreach ($people as $person) {
                $studentBranchId = DB::table('responsible_person_student')
                    ->join('students', 'students.id', '=', 'responsible_person_student.student_id')
                    ->where('responsible_person_student.responsible_person_id', $person->id)
                    ->orderByDesc('responsible_person_student.is_primary')
                    ->value('students.branch_id');
                DB::table('responsible_persons')->where('id', $person->id)->update([
                    'branch_id' => $studentBranchId ?? $mainBranchId,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('responsible_persons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
