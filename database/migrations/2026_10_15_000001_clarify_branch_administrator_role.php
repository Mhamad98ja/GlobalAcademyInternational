<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'super-administrator')->update([
            'label' => 'Branch Administrator',
            'description' => 'Branch operations and user management within assigned branches; global configuration is reserved for the Global Super Administrator.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'super-administrator')->update([
            'label' => 'Branch Super Administrator',
            'description' => 'Full operational and financial administration within assigned branches.',
            'updated_at' => now(),
        ]);
    }
};
