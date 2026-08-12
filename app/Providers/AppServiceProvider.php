<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\InvoicePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Render terminates TLS before forwarding requests to the container.
        // Force generated Vite assets, form actions, and links to HTTPS so
        // browsers do not block them as mixed content.
        if (env('RENDER', false)) {
            URL::forceScheme('https');
        }

        Gate::before(fn (User $user, string $ability): ?bool => $ability === 'manage-branches' ? null : ($user->hasRole('super-administrator') ? true : null));
        Gate::define('manage-branches', fn (User $user): bool => $user->isControllerAdministrator());
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
