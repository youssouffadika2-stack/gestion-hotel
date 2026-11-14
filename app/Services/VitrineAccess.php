<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Accès automatique des hôtels inscrits via la page d'accueil (administration générale) :
 *  - paiement de la publicité en cours  => actif
 *  - période payée terminée             => suspendu (message : appeler l'entreprise de gestion)
 *  - suspendu à la main / pas encore payé / pas encore validé => suspendu ou en attente
 * Les hôtels sans lien (vitrine_id) ne sont pas concernés.
 */
class VitrineAccess
{
    /** @return array{status:string, reason:string} */
    public function stateFor(object $row): array
    {
        if ($row->statut === 'suspendu') {
            return ['status' => Hotel::SUSPENDED, 'reason' => 'manual'];
        }
        if ($row->statut === 'en_attente') {
            return ['status' => Hotel::PENDING, 'reason' => 'validation'];
        }
        if (! $row->pub_fin) {
            return ['status' => Hotel::PENDING, 'reason' => 'payment'];
        }

        return $row->pub_fin >= now()->toDateString()
            ? ['status' => Hotel::ACTIVE, 'reason' => 'paid']
            : ['status' => Hotel::SUSPENDED, 'reason' => 'expired'];
    }

    /** Recalcule le statut des hôtels de l'utilisateur et le met à jour s'il a changé. */
    public function syncFor(User $user): void
    {
        $hotels = $user->hotels()->whereNotNull('hotels.vitrine_id')->get(['hotels.id', 'hotels.status', 'hotels.vitrine_id', 'hotels.activated_at']);
        foreach ($hotels as $hotel) {
            $row = DB::connection('vitrine')->table('hotels')->where('id', $hotel->vitrine_id)->first(['statut', 'pub_fin']);
            if (! $row) {
                continue;
            }
            $state = $this->stateFor($row)['status'];
            if ($state !== $hotel->status) {
                Hotel::whereKey($hotel->id)->update([
                    'status' => $state,
                    'activated_at' => $state === Hotel::ACTIVE ? ($hotel->activated_at ?? now()) : $hotel->activated_at,
                ]);
            }
        }
    }

    /** Raison de l'indisponibilité pour un hôtel lié (expired, manual, payment, validation) ou null. */
    public function reasonFor(Hotel $hotel): ?string
    {
        if (! $hotel->vitrine_id) {
            return null;
        }
        $row = DB::connection('vitrine')->table('hotels')->where('id', $hotel->vitrine_id)->first(['statut', 'pub_fin']);

        return $row ? $this->stateFor($row)['reason'] : null;
    }

    /** Coordonnées de l'entreprise de gestion (renseignées dans l'administration générale). */
    public function support(): array
    {
        try {
            $p = DB::connection('vitrine')->table('parametres')->pluck('valeur', 'cle');
        } catch (\Throwable) {
            $p = collect();
        }

        return [
            'name' => $p['entreprise'] ?? config('app.name'),
            'phone' => $p['telephone'] ?? '',
            'email' => $p['email'] ?? '',
        ];
    }
}
