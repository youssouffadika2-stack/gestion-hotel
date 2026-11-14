<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DONNÉES DE DÉMONSTRATION UNIQUEMENT — ne jamais exécuter en production.
 * Usage : php artisan db:seed --class=DemoHotelSeeder
 *
 * Comptes créés (mot de passe : password) :
 *  - admin@hotelpro.test      propriétaire de 2 hôtels (teste le sélecteur d'hôtel)
 *  - reception@hotelpro.test  réceptionniste d'un seul hôtel (ne voit pas les finances)
 *  - autre@hotelpro.test      client d'un AUTRE compte (teste l'isolation multi-tenant)
 *  - serveur@hotelpro.test    employé du Restaurant ; barman@hotelpro.test employé du Bar
 */
class DemoHotelSeeder extends Seeder
{
    private const NAMES = [
        'Kouassi Yao', 'Traoré Aminata', 'Koné Ibrahim', 'Bamba Fatou', 'N\'Guessan Marc', 'Diallo Mariam',
        'Ouattara Seydou', 'Yapi Christelle', 'Coulibaly Adama', 'Kouamé Eric', 'Soro Awa', 'Gnagne Patrick',
        'Doumbia Salif', 'Aka Nadège', 'Tano Franck', 'Bakayoko Rokia',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Seeder de démonstration refusé en production.');

            return;
        }

        mt_srand(2026); // données reproductibles

        $tenant = Tenant::create(['name' => 'Groupe Ivoire Hospitality']);
        $owner = $this->user($tenant, 'Awa Konaté', 'admin@hotelpro.test');
        $reception = $this->user($tenant, 'Jean Kouadio', 'reception@hotelpro.test');

        $h1 = Hotel::create(['tenant_id' => $tenant->id, 'name' => 'Hôtel Ivoire Palace', 'city' => 'Abidjan']);
        $h2 = Hotel::create(['tenant_id' => $tenant->id, 'name' => 'Résidence Les Palmiers', 'city' => 'Grand-Bassam']);
        $owner->hotels()->attach([$h1->id => ['role' => 'owner'], $h2->id => ['role' => 'owner']]);
        $reception->hotels()->attach($h1->id, ['role' => 'receptionist']);

        $this->populate($h1, $owner, 30);
        $this->populate($h2, $owner, 14);

        // Autre client SaaS, totalement séparé.
        $other = Tenant::create(['name' => 'Auberge du Sud SARL']);
        $otherUser = $this->user($other, 'Moussa Sylla', 'autre@hotelpro.test');
        $h3 = Hotel::create(['tenant_id' => $other->id, 'name' => 'Auberge du Sud', 'city' => 'San-Pédro']);
        $otherUser->hotels()->attach($h3->id, ['role' => 'owner']);
        $this->populate($h3, $otherUser, 10);

        $this->call(DemoDepartmentsSeeder::class);
    }

    private function user(Tenant $tenant, string $name, string $email): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => Hash::make('password')]);
        $user->tenant_id = $tenant->id;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function populate(Hotel $hotel, User $user, int $roomCount): void
    {
        $base = ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id];

        $types = collect([['Standard', 25000], ['Supérieure', 40000], ['Suite', 75000]])
            ->map(fn ($t) => RoomType::create($base + ['name' => $t[0], 'base_price' => $t[1]]));

        $rooms = collect();
        for ($i = 1; $i <= $roomCount; $i++) {
            $rooms->push(Room::create($base + [
                'room_type_id' => $types[$i % 3]->id,
                'number' => (string) (100 * (intdiv($i - 1, 10) + 1) + (($i - 1) % 10) + 1),
                'status' => Room::AVAILABLE,
            ]));
        }

        $guests = collect(self::NAMES)->map(fn ($n) => Guest::create($base + [
            'name' => $n,
            'phone' => '07'.mt_rand(10000000, 99999999),
        ]));

        // Séjours en cours : chambres occupées.
        $occupied = $rooms->shuffle()->take((int) round($roomCount * 0.4));
        foreach ($occupied as $room) {
            $room->update(['status' => Room::OCCUPIED]);
            $this->reservation($hotel, $user, $base, $guests->random(), $room, Reservation::CHECKED_IN, now()->subDays(mt_rand(0, 3)), mt_rand(1, 4));
        }

        // Maintenance.
        $rooms->whereNotIn('id', $occupied->pluck('id'))->shuffle()->take(max(1, (int) ($roomCount * 0.08)))
            ->each(fn ($r) => $r->update(['status' => Room::MAINTENANCE]));

        // Historique sur 12 mois (de plus en plus dense).
        $statuses = [Reservation::COMPLETED, Reservation::COMPLETED, Reservation::COMPLETED, Reservation::CONFIRMED, Reservation::PENDING, Reservation::CANCELLED];
        $total = $roomCount * 6;
        for ($i = 0; $i < $total; $i++) {
            $daysAgo = (int) (365 * (mt_rand() / mt_getrandmax()) ** 1.8);
            $status = $daysAgo < 2 ? [Reservation::PENDING, Reservation::CONFIRMED][mt_rand(0, 1)] : $statuses[mt_rand(0, 5)];
            $this->reservation($hotel, $user, $base, $guests->random(), $rooms->random(), $status, now()->subDays($daysAgo)->setTime(mt_rand(8, 20), mt_rand(0, 59)), mt_rand(1, 5));
        }

        // Dépenses.
        $labels = ['Électricité' => 'charges', 'Eau' => 'charges', 'Salaires' => 'personnel', 'Produits d\'entretien' => 'stock', 'Maintenance' => 'travaux', 'Internet' => 'charges'];
        for ($i = 0; $i < 70; $i++) {
            $label = array_rand($labels);
            Expense::create($base + [
                'label' => $label, 'category' => $labels[$label],
                'amount' => mt_rand(10, 250) * 1000,
                'spent_at' => now()->subDays((int) (365 * (mt_rand() / mt_getrandmax()) ** 1.5))->toDateString(),
                'created_by' => $user->id,
            ]);
        }

        // Stocks (les services / ventes sont créés par DemoDepartmentsSeeder).
        foreach ([['Savon', 'unité', 120, 40], ['Serviettes', 'unité', 18, 20], ['Papier toilette', 'rouleau', 300, 100]] as [$n, $u, $q, $a]) {
            \App\Models\StockItem::create($base + ['name' => $n, 'unit' => $u, 'quantity' => $q, 'alert_threshold' => $a]);
        }

        // Activités récentes.
        $acts = [
            ['reservation_created', 'Nouvelle réservation créée'], ['payment_recorded', 'Paiement enregistré'],
            ['guest_arrived', 'Arrivée d\'un client'], ['guest_departed', 'Départ d\'un client'],
            ['room_added', 'Ajout d\'une chambre'], ['reservation_updated', 'Modification d\'une réservation'],
        ];
        foreach (range(0, 7) as $i) {
            [$type, $desc] = $acts[$i % 6];
            $log = ActivityLog::create($base + ['user_id' => $user->id, 'type' => $type, 'description' => $desc]);
            $log->forceFill(['created_at' => now()->subMinutes($i * 47 + 5)])->save();
        }
    }

    private function reservation(Hotel $hotel, User $user, array $base, Guest $guest, Room $room, string $status, $createdAt, int $nights): void
    {
        $price = $room->type?->base_price ?? 30000;
        $checkIn = $createdAt->copy()->addDays(mt_rand(0, 2))->startOfDay();
        $total = $price * $nights;

        $r = new Reservation($base + [
            'code' => 'R-'.strtoupper(Str::random(6)),
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'check_in' => $checkIn,
            'check_out' => $checkIn->copy()->addDays($nights),
            'total_amount' => $total,
            'status' => $status,
            'created_by' => $user->id,
        ]);
        $r->created_at = $createdAt;
        $r->updated_at = $createdAt;
        $r->save();

        if (in_array($status, [Reservation::COMPLETED, Reservation::CHECKED_IN, Reservation::CONFIRMED], true)) {
            $paid = $status === Reservation::COMPLETED ? $total : (int) round($total * 0.5, -3);
            Payment::create($base + [
                'reservation_id' => $r->id,
                'amount' => max($paid, 5000),
                'method' => ['cash', 'mobile_money', 'card'][mt_rand(0, 2)],
                'paid_at' => $createdAt->copy()->addHours(mt_rand(0, 6)),
                'created_by' => $user->id,
            ]);
        }
    }
}
