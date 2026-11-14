<?php

namespace App\Services;

use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Source de vérité de l'hôtel actif.
 *
 * L'identifiant mémorisé en session n'est JAMAIS cru : il est revalidé à chaque
 * requête contre les hôtels réellement accessibles à l'utilisateur connecté.
 * Seuls les hôtels ACTIFS sont accessibles : un hôtel en attente d'activation ou
 * suspendu par l'administrateur de la plateforme n'ouvre aucun accès.
 */
class CurrentHotel
{
    public const SESSION_KEY = 'current_hotel_id';

    private ?Hotel $hotel = null;
    private bool $resolved = false;

    public function get(): ?Hotel
    {
        if ($this->resolved) {
            return $this->hotel;
        }

        $user = Auth::user();
        if (! $user) {
            return null; // pas de mémorisation : l'utilisateur peut se connecter ensuite
        }

        $this->resolved = true;

        $accessible = $user->hotels()->where('hotels.status', Hotel::ACTIVE);
        $hotel = null;

        if ($id = session(self::SESSION_KEY)) {
            $hotel = (clone $accessible)->whereKey($id)->first();
        }

        $hotel ??= $accessible->orderBy('hotels.name')->first();

        if ($hotel) {
            session([self::SESSION_KEY => $hotel->id]);
        } else {
            session()->forget(self::SESSION_KEY);
        }

        return $this->hotel = $hotel;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    /** Change d'hôtel, uniquement si l'utilisateur y a accès et que l'hôtel est actif (403 sinon). */
    public function switchTo(int $hotelId): Hotel
    {
        $hotel = Auth::user()->hotels()->where('hotels.status', Hotel::ACTIVE)->whereKey($hotelId)->first();

        abort_unless($hotel && Gate::allows('view', $hotel), 403);

        session([self::SESSION_KEY => $hotel->id]);
        $this->hotel = $hotel;
        $this->resolved = true;

        return $hotel;
    }
}
