<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Une instance par requête (et par requête Livewire) : pas de fuite entre utilisateurs.
        $this->app->scoped(\App\Services\CurrentHotel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Anti-abus des pages publiques (par adresse IP)
        \Illuminate\Support\Facades\RateLimiter::for('booking', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(6)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('tracking', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip()));

        // Toutes les pages Livewire "plein écran" utilisent le layout HOTELPRO.
        config(['livewire.layout' => 'components.layouts.hotelpro']);
    }
}
