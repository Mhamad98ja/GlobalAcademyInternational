<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_not_blocked_while_auth_rate_limiting_is_disabled(): void
    {
        config()->set('auth.rate_limiting', false);
        $user = User::factory()->create([
            'email' => 'tester@example.test',
            'password' => Hash::make('Password123@'),
            'is_active' => true,
        ]);
        $key = Str::transliterate(Str::lower($user->email.'|127.0.0.1'));

        foreach (range(1, 5) as $attempt) {
            RateLimiter::hit($key, 900);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Password123@',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_request_explains_when_resend_is_not_configured(): void
    {
        config()->set('mail.default', 'resend');
        config()->set('services.resend.key', null);
        $user = User::factory()->create(['is_active' => true]);

        $this->post(route('password.email'), [
            'delivery_method' => 'email',
            'email' => $user->email,
        ])->assertSessionHasErrors([
            'email' => 'Resend email delivery is not configured. Add RESEND_API_KEY in the service environment settings.',
        ]);
    }
}
