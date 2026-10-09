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
            ->where('value', 'Global Academy International')
            ->update(['value' => 'SBM', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Do not overwrite a school name chosen by an SBM administrator.
    }
};
