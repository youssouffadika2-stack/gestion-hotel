<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;

/**
 * Droits calculés par hôtel via le pivot hotel_user.
 *
 * Rôles : owner (administrateur de l'hôtel, voit tout et donne les accès), manager,
 * accountant, receptionist, staff (employé de service : Restaurant, Bar…).
 *
 * Un administrateur de plateforme n'obtient AUCUN accès aux données d'un hôtel par ce biais.
 */
class HotelPolicy
{
    private const ROLE_ABILITIES = [
        'view' => ['owner', 'manager', 'accountant', 'receptionist', 'staff'],
        'viewSales' => ['owner', 'manager', 'accountant', 'receptionist', 'staff'],
        'viewReservations' => ['owner', 'manager', 'accountant', 'receptionist'],
        'viewFinance' => ['owner', 'manager', 'accountant'],
        'manageOperations' => ['owner', 'manager', 'receptionist'],
        'recordPayment' => ['owner', 'manager', 'accountant', 'receptionist'],
        'manageCatalog' => ['owner', 'manager'],
        'closeDay' => ['owner', 'manager'],
        'manageHotel' => ['owner'],
    ];

    private function has(User $user, Hotel $hotel, string $ability): bool
    {
        return in_array($user->roleIn($hotel), self::ROLE_ABILITIES[$ability], true);
    }

    public function view(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'view'); }
    public function viewSales(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'viewSales'); }
    public function viewReservations(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'viewReservations'); }
    public function viewFinance(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'viewFinance'); }
    public function manageOperations(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'manageOperations'); }
    public function recordPayment(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'recordPayment'); }
    public function manageCatalog(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'manageCatalog'); }
    public function closeDay(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'closeDay'); }
    public function manageHotel(User $user, Hotel $hotel): bool { return $this->has($user, $hotel, 'manageHotel'); }

    /**
     * Peut travailler dans ce service (enregistrer des ventes, faire son point) ?
     * Gate::allows('operate', [$hotel, $department])
     */
    public function operate(User $user, Hotel $hotel, Department $department): bool
    {
        if ($department->hotel_id !== $hotel->id) {
            return false;
        }

        $role = $user->roleIn($hotel);

        return match (true) {
            in_array($role, ['owner', 'manager'], true) => true,
            $role === 'receptionist' && $department->isReception() => true,
            in_array($role, ['receptionist', 'staff'], true) => $user->departments()->whereKey($department->id)->exists(),
            default => false, // le comptable consulte mais n'opère pas
        };
    }
}
