<?php

namespace Database\Seeders;

use App\Models\DayClosure;
use App\Models\Department;
use App\Models\DepartmentClosure;
use App\Models\Hotel;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\DayClosingService;
use App\Services\HotelProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DONNÉES DE DÉMONSTRATION UNIQUEMENT.
 * Ajoute aux hôtels existants : services (Réception, Restaurant, Bar, Blanchisserie), catalogue,
 * ventes des 15 derniers jours, points déjà clôturés pour les jours passés, et deux employés de service.
 * Usage : php artisan db:seed --class=DemoDepartmentsSeeder
 */
class DemoDepartmentsSeeder extends Seeder
{
    private const CATALOG = [
        'restaurant' => [['Plat du jour', 6000], ['Poulet braisé', 5500], ['Attiéké poisson', 4500], ['Petit-déjeuner', 3500], ['Dessert', 2000]],
        'bar' => [['Bière', 1500], ['Jus naturel', 1000], ['Cocktail maison', 3500], ['Eau minérale', 500]],
        'laundry' => [['Lavage chemise', 1000], ['Costume', 4000], ['Robe', 2500]],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Seeder de démonstration refusé en production.');

            return;
        }

        mt_srand(77);
        $closing = app(DayClosingService::class);

        foreach (Hotel::all() as $hotel) {
            if (Sale::where('hotel_id', $hotel->id)->exists()) {
                continue; // déjà fait
            }

            app(HotelProvisioner::class)->createDefaultDepartments($hotel);
            $departments = Department::where('hotel_id', $hotel->id)->get()->keyBy('type');
            $base = ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id];

            // Catalogue rattaché aux services (on remplace l'ancien catalogue de démo sans service).
            Service::where('hotel_id', $hotel->id)->whereNull('department_id')->delete();
            foreach (self::CATALOG as $type => $items) {
                foreach ($items as [$name, $price]) {
                    Service::create($base + ['department_id' => $departments[$type]->id, 'name' => $name, 'price' => $price]);
                }
            }

            $owner = $hotel->users()->wherePivot('role', 'owner')->first();
            $methods = ['cash', 'cash', 'mobile_money', 'card'];

            for ($ago = 14; $ago >= 0; $ago--) {
                $day = today()->subDays($ago);
                foreach (self::CATALOG as $type => $items) {
                    $services = Service::where('hotel_id', $hotel->id)->where('department_id', $departments[$type]->id)->get();
                    foreach (range(1, mt_rand(3, 8)) as $_) {
                        $service = $services->random();
                        $qty = mt_rand(1, 4);
                        $soldAt = $ago === 0
                            ? max($day->copy()->setTime(7, 0), now()->subMinutes(mt_rand(5, 400)))
                            : $day->copy()->setTime(mt_rand(8, 22), mt_rand(0, 59));
                        Sale::create($base + [
                            'department_id' => $departments[$type]->id, 'service_id' => $service->id, 'label' => $service->name,
                            'quantity' => $qty, 'unit_price' => $service->price, 'amount' => $qty * $service->price,
                            'method' => $methods[array_rand($methods)], 'sold_at' => $soldAt, 'business_date' => $day->toDateString(),
                            'created_by' => $owner?->id,
                        ]);
                    }
                }

                // Les jours passés sont clôturés ; aujourd'hui reste ouvert.
                if ($ago > 0) {
                    $revenue = 0;
                    foreach ($closing->departments($hotel) as $d) {
                        $t = $closing->departmentTotals($hotel, $d, $day->toDateString());
                        $revenue += $t['total'];
                        DepartmentClosure::create($base + [
                            'department_id' => $d->id, 'business_date' => $day->toDateString(), 'total' => $t['total'],
                            'cash_expected' => $t['cash'], 'cash_counted' => $t['cash'], 'operations' => $t['operations'],
                            'closed_by' => $owner?->id, 'closed_at' => $day->copy()->setTime(23, 0),
                        ]);
                    }
                    DayClosure::create($base + [
                        'business_date' => $day->toDateString(), 'total_revenue' => $revenue, 'total_expenses' => 0,
                        'closed_by' => $owner?->id, 'closed_at' => $day->copy()->setTime(23, 30),
                    ]);
                }
            }

            // Deux employés de service pour le premier hôtel de démonstration.
            if ($hotel->name === 'Hôtel Ivoire Palace') {
                foreach ([['Koffi Serveur', 'serveur@hotelpro.test', 'restaurant'], ['Yao Barman', 'barman@hotelpro.test', 'bar']] as [$name, $email, $type]) {
                    $user = User::where('email', $email)->first() ?? new User(['name' => $name, 'email' => $email, 'password' => Hash::make('password')]);
                    if (! $user->exists) {
                        $user->tenant_id = $hotel->tenant_id;
                        $user->email_verified_at = now();
                        $user->save();
                    }
                    $user->hotels()->syncWithoutDetaching([$hotel->id => ['role' => 'staff']]);
                    $user->departments()->syncWithoutDetaching([$departments[$type]->id]);
                }
            }
        }
    }
}
