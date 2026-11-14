<?php

namespace Tests\Feature;

use App\Livewire\OnlineRequests;
use App\Livewire\Platform\Hotels;
use App\Livewire\Topbar\Notifications;
use App\Mail\HotelAccessMail;
use App\Mail\NewOnlineReservationMail;
use App\Mail\ReservationStatusMail;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OnlineBookingTest extends TestCase
{
    use RefreshDatabase;

    private function hotel(string $status = 'active', ?int $vitrineId = null): Hotel
    {
        $hotel = Hotel::create(['tenant_id' => Tenant::create(['name' => 'T'.uniqid()])->id, 'name' => 'Hôtel '.uniqid(), 'city' => 'Abidjan', 'status' => $status]);
        if ($vitrineId) {
            $hotel->vitrine_id = $vitrineId;
            $hotel->save();
        }

        return $hotel;
    }

    private function member(Hotel $hotel, string $role): User
    {
        $user = new User(['name' => ucfirst($role).' '.uniqid(), 'email' => $role.uniqid().'@t.test', 'password' => 'password']);
        $user->tenant_id = $hotel->tenant_id;
        $user->email_verified_at = now();
        $user->save();
        $user->hotels()->attach($hotel->id, ['role' => $role]);

        return $user;
    }

    /** Demande en ligne d'un client, en attente. */
    private function onlineRequest(Hotel $hotel, string $email = 'client@mail.test', array $override = []): Reservation
    {
        $base = ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id];
        $type = RoomType::create($base + ['name' => 'Standard', 'base_price' => 25000]);
        $room = Room::create($base + ['number' => (string) random_int(100, 999), 'room_type_id' => $type->id, 'status' => 'available']);
        $guest = Guest::create($base + ['name' => 'Client Web', 'phone' => '0700000000', 'email' => $email]);

        return Reservation::create($base + $override + [
            'code' => 'R-'.strtoupper(substr(md5(uniqid()), 0, 6)), 'guest_id' => $guest->id, 'room_id' => $room->id,
            'check_in' => today()->addDays(3), 'check_out' => today()->addDays(5), 'total_amount' => 50000, 'status' => 'pending',
            'source' => 'online', 'guest_message' => 'Arrivée tardive vers 22h', 'guests_count' => 2,
        ]);
    }

    /* --------------------------- E-mail à l'activation --------------------------- */

    public function test_bridge_sends_the_activation_email_with_the_password(): void
    {
        Mail::fake();
        config(['hotelpro.bridge_secret' => 's3cret-partage']);
        $hotel = $this->hotel('active', 7);
        $owner = $this->member($hotel, 'owner');

        $this->postJson('/api/internal/hotel-access', ['vitrine_id' => 7, 'event' => 'activated', 'password' => 'Mdp-Provisoire1'], ['X-Bridge-Secret' => 's3cret-partage'])
            ->assertOk()->assertJson(['sent' => true]);

        Mail::assertSent(HotelAccessMail::class, fn ($m) => $m->hasTo($owner->email) && $m->password === 'Mdp-Provisoire1' && $m->event === 'activated');
    }

    public function test_bridge_refuses_wrong_secret_and_inactive_hotels(): void
    {
        Mail::fake();
        config(['hotelpro.bridge_secret' => 's3cret-partage']);
        $pending = $this->hotel('pending', 8);
        $this->member($pending, 'owner');

        $this->postJson('/api/internal/hotel-access', ['vitrine_id' => 8, 'event' => 'activated'], ['X-Bridge-Secret' => 'mauvais'])->assertForbidden();
        $this->postJson('/api/internal/hotel-access', ['vitrine_id' => 8, 'event' => 'activated'])->assertForbidden();
        $this->postJson('/api/internal/hotel-access', ['vitrine_id' => 8, 'event' => 'activated'], ['X-Bridge-Secret' => 's3cret-partage'])->assertStatus(409);
        $this->postJson('/api/internal/hotel-access', ['vitrine_id' => 999, 'event' => 'activated'], ['X-Bridge-Secret' => 's3cret-partage'])->assertNotFound();

        config(['hotelpro.bridge_secret' => null]);
        $this->postJson('/api/internal/hotel-access', ['vitrine_id' => 8, 'event' => 'activated'], ['X-Bridge-Secret' => ''])->assertStatus(503);

        Mail::assertNothingSent();
    }

    public function test_platform_admin_activation_sends_an_email_once(): void
    {
        Mail::fake();
        $hotel = $this->hotel('pending');
        $owner = $this->member($hotel, 'owner');
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($admin);

        Livewire::test(Hotels::class)->call('activate', $hotel->id)->call('activate', $hotel->id);

        Mail::assertSent(HotelAccessMail::class, 1); // la 2e activation (déjà actif) n'envoie rien
        Mail::assertSent(HotelAccessMail::class, fn ($m) => $m->hasTo($owner->email) && $m->password === null);
    }

    public function test_platform_admin_can_email_the_new_password(): void
    {
        Mail::fake();
        $hotel = $this->hotel('active');
        $owner = $this->member($hotel, 'owner');
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($admin);

        Livewire::test(Hotels::class)->call('openAccess', $hotel->id)->set('newPassword', 'Nouveau-Acces-9')->call('saveAccess')
            ->assertSet('emailSent', true);

        Mail::assertSent(HotelAccessMail::class, fn ($m) => $m->hasTo($owner->email) && $m->password === 'Nouveau-Acces-9' && $m->event === 'password_reset');
    }

    /* --------------------------- Boîte « Demandes en ligne » --------------------------- */

    public function test_inbox_lists_requests_and_confirming_warns_the_client(): void
    {
        Mail::fake();
        $hotel = $this->hotel();
        $receptionist = $this->member($hotel, 'receptionist');
        $request = $this->onlineRequest($hotel, 'awa.client@mail.test');
        $this->actingAs($receptionist);

        Livewire::test(OnlineRequests\Index::class)
            ->assertSee('Client Web')->assertSee('Arrivée tardive vers 22h')->assertSee('Nouveau')
            ->call('confirm', $request->id);

        $request->refresh();
        $this->assertSame('confirmed', $request->status);
        $this->assertNotNull($request->read_at); // traiter une demande la marque comme lue
        Mail::assertSent(ReservationStatusMail::class, fn ($m) => $m->hasTo('awa.client@mail.test') && $m->outcome === 'confirmed');
    }

    public function test_inbox_search_unread_checkin_and_double_booking_guard(): void
    {
        Mail::fake();
        $hotel = $this->hotel();
        $this->actingAs($this->member($hotel, 'receptionist'));
        $a = $this->onlineRequest($hotel, 'a@mail.test');
        $b = $this->onlineRequest($hotel, 'b@mail.test', ['room_id' => $a->room_id]); // même chambre, mêmes dates

        Livewire::test(OnlineRequests\Index::class)->set('filter', 'all')->set('search', $a->code)
            ->assertViewHas('requests', fn ($p) => $p->total() === 1);

        $c = Livewire::test(OnlineRequests\Index::class)->set('filter', 'all')->call('confirm', $a->id)->call('confirm', $b->id);
        $this->assertSame('confirmed', $a->fresh()->status);
        $this->assertSame('pending', $b->fresh()->status); // refusée : chambre déjà prise

        $c->call('markUnread', $a->id);
        $this->assertNull($a->fresh()->read_at);
        $c->call('checkIn', $a->id);
        $this->assertSame('checked_in', $a->fresh()->status);
        $c->call('checkOut', $a->id);
        $this->assertSame('completed', $a->fresh()->status);
    }

    public function test_hotel_message_reaches_the_client_by_email_and_tracking(): void
    {
        Mail::fake();
        $hotel = $this->hotel();
        $this->actingAs($this->member($hotel, 'owner'));
        $r = $this->onlineRequest($hotel, 'msg@mail.test');

        Livewire::test(OnlineRequests\Index::class)
            ->call('openAnswer', $r->id, 'confirm')->assertSet('showAnswer', true)
            ->set('answerMessage', 'Bienvenue, arrivée dès 14h.')->call('submitAnswer')
            ->assertSet('showAnswer', false);

        $this->assertSame('confirmed', $r->fresh()->status);
        $this->assertSame('Bienvenue, arrivée dès 14h.', $r->fresh()->hotel_message);
        Mail::assertSent(ReservationStatusMail::class, fn ($m) => $m->hasTo('msg@mail.test') && str_contains($m->render(), 'Bienvenue, arrivée dès 14h.'));

        auth()->logout();
        $this->post(route('vitrine.track.lookup'), ['code' => $r->code, 'email' => 'msg@mail.test'])
            ->assertOk()->assertSee('Message de l', false)->assertSee('Bienvenue, arrivée dès 14h.');
    }

    public function test_declining_warns_the_client_and_frees_the_dates(): void
    {
        Mail::fake();
        $hotel = $this->hotel();
        $owner = $this->member($hotel, 'owner');
        $request = $this->onlineRequest($hotel);
        $this->actingAs($owner);

        Livewire::test(OnlineRequests\Index::class)->call('decline', $request->id);

        $this->assertSame('cancelled', $request->fresh()->status);
        Mail::assertSent(ReservationStatusMail::class, fn ($m) => $m->outcome === 'cancelled');
    }

    public function test_accountant_and_staff_cannot_answer_requests(): void
    {
        Mail::fake();
        $hotel = $this->hotel();
        $accountant = $this->member($hotel, 'accountant');
        $employee = $this->member($hotel, 'staff');
        $request = $this->onlineRequest($hotel);

        $this->actingAs($accountant);
        Livewire::test(OnlineRequests\Index::class)->call('confirm', $request->id)->assertForbidden();

        $this->flushSession();
        $this->actingAs($employee)->get(route('online.index'))->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_a_hotel_only_sees_its_own_requests(): void
    {
        $mine = $this->hotel();
        $other = $this->hotel();
        $owner = $this->member($mine, 'owner');
        $this->onlineRequest($mine, 'a@a.test');
        $foreign = $this->onlineRequest($other, 'b@b.test');
        $this->actingAs($owner);

        Livewire::test(OnlineRequests\Index::class)->set('filter', 'all')->assertViewHas('requests', fn ($p) => $p->total() === 1);

        try {
            Livewire::test(OnlineRequests\Index::class)->call('confirm', $foreign->id);
            $this->fail('La demande d\'un autre hôtel ne doit pas être accessible.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertSame('pending', Reservation::withoutGlobalScopes()->find($foreign->id)->status);
        }
    }

    public function test_bell_counts_unread_online_requests(): void
    {
        $hotel = $this->hotel();
        $owner = $this->member($hotel, 'owner');
        $this->onlineRequest($hotel);
        $this->actingAs($owner);

        Livewire::test(Notifications::class)->assertSee('demande(s) de réservation en ligne')->assertViewHas('count', 1);
    }

    public function test_hotel_staff_is_emailed_on_a_new_online_request(): void
    {
        Mail::fake();
        $hotel = $this->hotel();
        $owner = $this->member($hotel, 'owner');
        $receptionist = $this->member($hotel, 'receptionist');
        $accountant = $this->member($hotel, 'accountant');
        $request = $this->onlineRequest($hotel);

        app(Notifier::class)->newOnlineReservation($request, $hotel, 'Client Web', '0700000000', 'client@mail.test', 'Ch. 101 (Standard)');

        Mail::assertSent(NewOnlineReservationMail::class, fn ($m) => $m->hasTo($owner->email) && $m->hasTo($receptionist->email) && ! $m->hasTo($accountant->email));
    }

    /* --------------------------- Suivi par le client --------------------------- */

    public function test_client_can_track_with_reference_and_email_only(): void
    {
        $request = $this->onlineRequest($this->hotel(), 'Awa.Client@Mail.test');

        $this->post(route('vitrine.track.lookup'), ['code' => strtolower($request->code), 'email' => 'awa.client@mail.test'])
            ->assertOk()->assertSee($request->code)->assertSee('En attente');

        // Mauvais e-mail ou mauvaise référence : même message, rien n'est révélé.
        $this->post(route('vitrine.track.lookup'), ['code' => $request->code, 'email' => 'autre@mail.test'])
            ->assertSessionHasErrors('code');
        $this->post(route('vitrine.track.lookup'), ['code' => 'R-ZZZZZZ', 'email' => 'awa.client@mail.test'])
            ->assertSessionHasErrors('code');
    }

    public function test_client_can_cancel_and_the_hotel_is_informed_in_its_log(): void
    {
        Mail::fake();
        $hotel = $this->hotel();
        $request = $this->onlineRequest($hotel, 'client@mail.test');

        $this->post(route('vitrine.track.cancel'), ['code' => $request->code, 'email' => 'client@mail.test'])
            ->assertOk()->assertSee('Annulée');

        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['hotel_id' => $hotel->id]);
        Mail::assertNothingSent(); // pas d'e-mail renvoyé au client lorsqu'il annule lui-même
    }

    public function test_client_cannot_cancel_with_a_wrong_email_or_after_the_stay(): void
    {
        $hotel = $this->hotel();
        $request = $this->onlineRequest($hotel, 'client@mail.test');
        $done = $this->onlineRequest($hotel, 'client@mail.test', ['status' => 'completed']);

        $this->post(route('vitrine.track.cancel'), ['code' => $request->code, 'email' => 'intrus@mail.test'])->assertNotFound();
        $this->assertSame('pending', $request->fresh()->status);

        $this->post(route('vitrine.track.cancel'), ['code' => $done->code, 'email' => 'client@mail.test'])
            ->assertOk()->assertSee('ne peut plus être annulée en ligne');
        $this->assertSame('completed', $done->fresh()->status);
    }

    public function test_staff_made_reservations_are_not_trackable_by_the_public(): void
    {
        $request = $this->onlineRequest($this->hotel(), 'client@mail.test', ['source' => 'staff']);

        $this->post(route('vitrine.track.lookup'), ['code' => $request->code, 'email' => 'client@mail.test'])->assertSessionHasErrors('code');
    }

    /* --------------------------- Disponibilité --------------------------- */

    public function test_availability_is_correct_even_when_a_logged_in_hotel_user_browses_another_hotel(): void
    {
        // Régression : le scope d'hôtel d'un utilisateur connecté ne doit pas fausser la disponibilité d'un AUTRE hôtel.
        $mine = $this->hotel();
        $theirs = $this->hotel();
        $owner = $this->member($mine, 'owner');
        $booked = $this->onlineRequest($theirs, 'x@x.test', ['status' => 'confirmed', 'check_in' => today()->addDays(10), 'check_out' => today()->addDays(12)]);
        $this->actingAs($owner);
        app(\App\Services\CurrentHotel::class)->get();

        $free = Room::withoutGlobalScopes()->where('hotel_id', $theirs->id)
            ->availableBetween(today()->addDays(10)->toDateString(), today()->addDays(12)->toDateString())->count();
        $freeLater = Room::withoutGlobalScopes()->where('hotel_id', $theirs->id)
            ->availableBetween(today()->addDays(20)->toDateString(), today()->addDays(22)->toDateString())->count();

        $this->assertSame(0, $free);      // la chambre est bien réservée sur ces dates
        $this->assertSame(1, $freeLater); // et libre ensuite
        $this->assertNotNull($booked);
    }
}
