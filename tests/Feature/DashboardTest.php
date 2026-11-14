<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\HotelSwitcher;
use App\Livewire\Dashboard\Index;
use App\Livewire\Dashboard\RecentReservations;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CurrentHotel;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role, ?Hotel $hotel = null): array
    {
        $tenant = Tenant::create(['name' => 'T'.uniqid()]);
        $hotel ??= Hotel::create(['tenant_id' => $tenant->id, 'name' => 'H'.uniqid()]);
        $user = new User(['name' => 'U', 'email' => uniqid().'@t.test', 'password' => 'password']);
        $user->tenant_id = $hotel->tenant_id;
        $user->email_verified_at = now();
        $user->save();
        $user->hotels()->attach($hotel->id, ['role' => $role]);

        return [$user, $hotel];
    }

    private function seedHotel(Hotel $hotel, int $rooms, int $payment): void
    {
        $base = ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id];
        for ($i = 1; $i <= $rooms; $i++) {
            Room::create($base + ['number' => (string) $i, 'status' => Room::AVAILABLE]);
        }
        $guest = Guest::create($base + ['name' => 'Client '.$hotel->name]);
        $res = Reservation::create($base + [
            'code' => 'R-'.$hotel->id, 'guest_id' => $guest->id,
            'check_in' => today(), 'check_out' => today()->addDay(), 'total_amount' => $payment, 'status' => 'confirmed',
        ]);
        Payment::create($base + ['reservation_id' => $res->id, 'amount' => $payment, 'paid_at' => now()]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_without_hotel_gets_403_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_dashboard_only_shows_data_of_the_active_hotel(): void
    {
        [$owner, $hotelA] = $this->member('owner');
        [, $hotelB] = $this->member('owner'); // autre tenant
        $this->seedHotel($hotelA, 3, 50000);
        $this->seedHotel($hotelB, 9, 999000);

        $this->actingAs($owner);

        Livewire::test(Index::class)
            ->assertSee('50 000 FCFA')
            ->assertDontSee('999 000')
            ->assertViewHas('stats', fn ($s) => $s['rooms_total'] === 3);

        // Même sans filtre explicite, le global scope isole les données.
        $this->assertSame(3, Room::count());
        $this->assertSame(1, Reservation::count());
    }

    public function test_user_cannot_switch_to_a_hotel_of_another_tenant(): void
    {
        [$owner] = $this->member('owner');
        [, $foreign] = $this->member('owner');

        $this->actingAs($owner);

        Livewire::test(HotelSwitcher::class)->call('switchTo', $foreign->id)->assertForbidden();
    }

    public function test_forged_session_hotel_id_is_ignored(): void
    {
        [$owner, $mine] = $this->member('owner');
        [, $foreign] = $this->member('owner');

        $this->actingAs($owner)->withSession([CurrentHotel::SESSION_KEY => $foreign->id]);

        $this->assertSame($mine->id, app(CurrentHotel::class)->id());
    }

    public function test_user_can_switch_between_own_hotels(): void
    {
        [$owner, $hotelA] = $this->member('owner');
        $hotelB = Hotel::create(['tenant_id' => $hotelA->tenant_id, 'name' => 'Second']);
        $owner->hotels()->attach($hotelB->id, ['role' => 'owner']);

        $this->actingAs($owner);

        Livewire::test(HotelSwitcher::class)->call('switchTo', $hotelB->id)->assertRedirect(route('dashboard'));
        $this->assertSame($hotelB->id, session(CurrentHotel::SESSION_KEY));
    }

    public function test_receptionist_does_not_see_finance(): void
    {
        [$user, $hotel] = $this->member('receptionist');
        $this->seedHotel($hotel, 2, 77000);

        $this->actingAs($user);

        Livewire::test(Index::class)
            ->assertDontSee('77 000')
            ->assertDontSee("Chiffre d'affaires")
            ->assertViewHas('stats', fn ($s) => $s['revenue'] === null);
    }

    public function test_period_filter_is_whitelisted(): void
    {
        [$owner] = $this->member('owner');
        $this->actingAs($owner);

        Livewire::test(Index::class)->call('setPeriod', "1; DROP TABLE users")->assertSet('period', '30d');
        Livewire::test(Index::class)->call('setPeriod', '7d')->assertSet('period', '7d');
        $this->assertSame('30d', DashboardService::normalizePeriod('nimportequoi'));
    }

    public function test_empty_hotel_shows_empty_states(): void
    {
        [$owner] = $this->member('owner');
        $this->actingAs($owner);

        Livewire::test(Index::class)->assertSee('Aucune chambre enregistrée');
        Livewire::test(RecentReservations::class)->assertSee('Aucune réservation pour le moment');
    }

    public function test_platform_admin_flag_gives_no_hotel_access(): void
    {
        [, $hotel] = $this->member('owner');
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin);

        $this->assertNull(app(CurrentHotel::class)->get());
        // Il est renvoyé vers son espace plateforme : aucune donnée d'hôtel n'est accessible.
        $this->get('/dashboard')->assertRedirect(route('platform.hotels'));
    }
}
