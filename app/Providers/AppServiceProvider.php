<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

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
        RateLimiter::for('login', function (Request $request) {
            return $this->perMinute(5, $request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return $this->perMinute(5, $request->ip());
        });

        RateLimiter::for('public-catalog', function (Request $request) {
            return $this->perMinute(60, $request->ip());
        });

        RateLimiter::for('whatsapp', function (Request $request) {
            return $this->perMinute(20, (string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('api', function (Request $request) {
            return $this->perMinute(120, (string) ($request->user()?->id ?: $request->ip()));
        });
    }

    private function perMinute(int $maxAttempts, ?string $key): Limit
    {
        return Limit::perMinute($maxAttempts)
            ->by($key ?: 'unknown')
            ->response(function (Request $request, array $headers): Response {
                return response()->json([
                    'message' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.',
                ], 429, $headers);
            });
    }
}
