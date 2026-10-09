<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->where('key', 'school_name')
            ->where('value', 'SBM')
            ->update(['value' => 'Global Academy International', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'school_name')
            ->where('value', 'Global Academy International')
            ->update(['value' => 'SBM', 'updated_at' => now()]);
    }
};
