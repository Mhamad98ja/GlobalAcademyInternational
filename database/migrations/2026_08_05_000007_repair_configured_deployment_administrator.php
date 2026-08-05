<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $email = (string) env('SBM_ADMIN_EMAIL', '');
        $password = (string) env('SBM_ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            return;
        }

        DB::table('users')
            ->where('email', $email)
            ->update([
                'name' => env('SBM_ADMIN_NAME', 'System Administrator'),
                'password' => Hash::make($password),
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The previous administrator password cannot be restored safely.
    }
};
