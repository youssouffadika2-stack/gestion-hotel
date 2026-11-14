<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use App\Services\CurrentHotel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHotelSelected
{
    public function __construct(private CurrentHotel $currentHotel)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->currentHotel->get()) {
            return $next($request);
        }

        $user = $request->user();

        // L'administrateur de la plateforme n'a pas d'hôtel : on l'envoie sur son espace.
        if ($user?->is_platform_admin) {
            return redirect()->route('platform.hotels');
        }

        // Pas d'accès : on explique pourquoi (en attente d'activation, suspendu ou aucun hôtel).
        $hotels = $user ? $user->hotels()->get(['hotels.id', 'hotels.name', 'hotels.status']) : collect();

        $state = match (true) {
            $hotels->contains('status', Hotel::PENDING) => 'pending',
            $hotels->contains('status', Hotel::SUSPENDED) => 'suspended',
            default => 'none',
        };

        return response()->view('no-hotel', ['state' => $state, 'hotelName' => $hotels->first()?->name], 403);
    }
}
