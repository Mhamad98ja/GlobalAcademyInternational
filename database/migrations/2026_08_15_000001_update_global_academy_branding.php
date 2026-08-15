<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $setting = DB::table('system_settings')->where('key', 'school_name');

        if ($setting->exists()) {
            $setting->update(['value' => 'Global Academy International', 'updated_at' => $now]);

            return;
        }

        DB::table('system_settings')->insert([
            'key' => 'school_name',
            'value' => 'Global Academy International',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // The previous school name is not recoverable safely.
    }
};
