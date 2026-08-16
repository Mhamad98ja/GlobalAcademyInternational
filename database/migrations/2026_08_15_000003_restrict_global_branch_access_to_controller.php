<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('is_controller_administrator', false)
            ->update(['has_all_branch_access' => false]);
    }

    public function down(): void
    {
        // Global branch access is intentionally reserved for the Controller Administrator.
    }
};
