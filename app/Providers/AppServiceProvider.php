<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
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
        Gate::define('viewUserActivityLogs', fn ($user) => $user?->role === 'admin');
        Gate::define('viewAutomacaoExecucoes', fn ($user) => $user?->role === 'admin');

        Event::listen(Login::class, function ($event) {
            $user = $event->user;
            $user->forceFill(['last_login_at' => now()])->save();

            // Log de login
            try {
                \App\Models\UserActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'auth.login',
                    'route_name' => request()->route()?->getName(),
                    'method' => request()->method(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'path' => request()->path(),
                    'metadata' => [
                        'request_id' => request()->header('X-Request-Id') ?? null,
                    ],
                ]);
            } catch (\Throwable $e) {
                // silencioso
            }
        });
    }
}
