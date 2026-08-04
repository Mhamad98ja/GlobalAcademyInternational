<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Please wait '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }

        $user = User::with('roles')->where('email', $request->string('email'))->first();
        if (! $user || ! $user->is_active || ! Hash::check($request->string('password'), $user->password)) {
            RateLimiter::hit($key, 900);

            return back()->withErrors(['email' => 'The supplied credentials are invalid.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been signed out.');
    }

    public function forgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request, WhatsAppService $whatsApp): RedirectResponse
    {
        $data = $request->validate([
            'delivery_method' => ['required', Rule::in(['email', 'whatsapp'])],
            'email' => [Rule::requiredIf($request->input('delivery_method') === 'email'), 'nullable', 'email', 'max:255'],
            'whatsapp_number' => [Rule::requiredIf($request->input('delivery_method') === 'whatsapp'), 'nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
        ]);

        if ($data['delivery_method'] === 'email') {
            return $this->sendEmailReset((string) $data['email']);
        }

        return $this->sendWhatsAppReset((string) $data['whatsapp_number'], $request, $whatsApp);
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);
        if (! User::query()->where('email', $data['email'])->where('is_active', true)->exists()) {
            return back()->withErrors(['email' => 'This password reset request is invalid or has expired.']);
        }

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.')
            : back()->withErrors(['email' => 'This password reset request is invalid or has expired.']);
    }

    public function whatsappResetForm(Request $request): View
    {
        return view('auth.reset-whatsapp-password', ['whatsappNumber' => $request->query('whatsapp_number')]);
    }

    public function whatsappReset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_number' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);
        $key = 'password-reset-whatsapp-verify:'.$data['whatsapp_number'].'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['otp' => 'Too many incorrect codes. Please request a new code and try again later.']);
        }

        $user = User::query()->where('whatsapp_number', $data['whatsapp_number'])->where('is_active', true)->first();
        $otp = $user?->passwordResetOtps()
            ->where('channel', 'whatsapp')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp || $otp->attempts >= 5 || ! Hash::check($data['otp'], $otp->code)) {
            $otp?->increment('attempts');
            RateLimiter::hit($key, 900);

            return back()->withErrors(['otp' => 'This code is invalid or has expired.']);
        }

        DB::transaction(function () use ($user, $otp, $data): void {
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            $otp->update(['used_at' => now()]);
            $user->passwordResetOtps()->whereNull('used_at')->delete();
        });
        RateLimiter::clear($key);

        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
    }

    private function sendEmailReset(string $email): RedirectResponse
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['email' => 'Email delivery is not configured. Add the Gmail SMTP settings before sending reset links.']);
        }

        $user = User::query()->where('email', $email)->where('is_active', true)->first();
        if ($user) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (Throwable $exception) {
                report($exception);

                return back()->withErrors(['email' => 'The reset email could not be sent. Check the Gmail mail configuration and try again.']);
            }
        }

        return back()->with('success', 'If the email belongs to an active account, a reset link has been sent.');
    }

    private function sendWhatsAppReset(string $whatsappNumber, Request $request, WhatsAppService $whatsApp): RedirectResponse
    {
        $key = 'password-reset-whatsapp-send:'.$whatsappNumber.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['whatsapp_number' => 'Too many requests. Please try again later.']);
        }

        $user = User::query()->where('whatsapp_number', $whatsappNumber)->where('is_active', true)->first();
        if (! $user) {
            RateLimiter::hit($key, 900);

            return back()->with('success', 'If the WhatsApp number belongs to an active account, a reset code has been sent.');
        }

        $code = (string) random_int(100000, 999999);
        $otp = DB::transaction(function () use ($user, $code): PasswordResetOtp {
            $user->passwordResetOtps()->where('channel', 'whatsapp')->delete();

            return $user->passwordResetOtps()->create([
                'channel' => 'whatsapp',
                'code' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        try {
            $whatsApp->sendPasswordResetOtp($whatsappNumber, $code);
        } catch (Throwable $exception) {
            $otp->delete();
            report($exception);

            return back()->withErrors(['whatsapp_number' => 'The WhatsApp reset message could not be sent. Check the WhatsApp Cloud API configuration and try again.']);
        }

        RateLimiter::hit($key, 900);

        return redirect()->route('password.whatsapp', ['whatsapp_number' => $whatsappNumber])
            ->with('success', 'A six-digit reset code was sent to WhatsApp. It expires in 10 minutes.');
    }
}
