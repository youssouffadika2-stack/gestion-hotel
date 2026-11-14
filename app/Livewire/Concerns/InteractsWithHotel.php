<?php

namespace App\Livewire\Concerns;

use App\Models\ActivityLog;
use App\Models\Hotel;
use App\Services\CurrentHotel;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;

/**
 * Outils communs des composants Livewire : l'hôtel est toujours relu côté serveur
 * (jamais depuis une propriété publique modifiable par le navigateur).
 */
trait InteractsWithHotel
{
    protected function hotel(): Hotel
    {
        $hotel = app(CurrentHotel::class)->get();
        abort_unless($hotel, 403);

        return $hotel;
    }

    /** Vérifie une capacité (policy) pour l'hôtel actif ; 403 sinon. */
    protected function authorizeHotel(string $ability): Hotel
    {
        $hotel = $this->hotel();
        Gate::authorize($ability, $hotel);

        return $hotel;
    }

    protected function logActivity(string $type, string $description): void
    {
        $hotel = $this->hotel();

        ActivityLog::create([
            'tenant_id' => $hotel->tenant_id,
            'hotel_id' => $hotel->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
        ]);
    }

    /** À appeler après toute écriture : invalide le cache des statistiques. */
    protected function dataChanged(): void
    {
        app(DashboardService::class)->flush($this->hotel());
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}
