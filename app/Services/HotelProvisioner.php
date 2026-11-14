<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crée un compte client complet : tenant + hôtel + administrateur (propriétaire) + services de base.
 */
class HotelProvisioner
{
    public const DEFAULT_DEPARTMENTS = [
        ['Réception', 'reception'],
        ['Restaurant', 'restaurant'],
        ['Bar', 'bar'],
        ['Blanchisserie', 'laundry'],
    ];

    /**
     * Inscription publique : l'hôtel est créé EN ATTENTE. Il ne sera utilisable qu'après
     * activation par l'administrateur de la plateforme.
     */
    public function registerOwner(User $user, string $hotelName, ?string $city = null): Hotel
    {
        return DB::transaction(function () use ($user, $hotelName, $city) {
            $tenant = Tenant::create(['name' => $hotelName]);

            $user->tenant_id = $tenant->id; // jamais depuis le navigateur
            $user->save();

            $hotel = Hotel::create([
                'tenant_id' => $tenant->id, 'name' => $hotelName, 'city' => $city ?: null,
                'status' => Hotel::PENDING,
            ]);
            $user->hotels()->attach($hotel->id, ['role' => 'owner']);
            $this->createDefaultDepartments($hotel);

            return $hotel;
        });
    }

    public function createDefaultDepartments(Hotel $hotel): void
    {
        foreach (self::DEFAULT_DEPARTMENTS as [$name, $type]) {
            Department::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $hotel->id, 'name' => $name],
                ['tenant_id' => $hotel->tenant_id, 'type' => $type, 'active' => true],
            );
        }
    }
}
