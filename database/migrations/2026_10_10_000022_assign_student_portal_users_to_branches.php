<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('users')
            ->join('students', 'students.id', '=', 'users.student_id')
            ->whereNotNull('students.branch_id')
            ->select('users.id as user_id', 'students.branch_id')
            ->orderBy('users.id')
            ->get()
            ->each(function (object $studentUser) use ($now): void {
                DB::table('branch_user')->insertOrIgnore([
                    'branch_id' => $studentUser->branch_id,
                    'user_id' => $studentUser->user_id,
                    'is_default' => true,
                    'is_branch_administrator' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('branch_user')
            ->where('is_default', true)
            ->where('is_branch_administrator', false)
            ->whereExists(fn ($users) => $users
                ->from('users')
                ->join('students', 'students.id', '=', 'users.student_id')
                ->whereColumn('users.id', 'branch_user.user_id')
                ->whereColumn('students.branch_id', 'branch_user.branch_id'))
            ->delete();
    }
};
