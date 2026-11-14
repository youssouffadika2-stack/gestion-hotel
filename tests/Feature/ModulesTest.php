<?php

namespace Tests\Feature;

use App\Livewire\Expenses;
use App\Livewire\Payments;
use App\Livewire\Reservations;
use App\Livewire\Stock;
use App\Livewire\Staff;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StockItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role, ?Hotel $hotel = null): array
    {
        $hotel ??= Hotel::create(['tenant_id' => Tenant::create(['name' => 'T'.uniqid()])->id, 'name' => 'H'.uniqid()]);
        $user = new User(['name' => 'U'.uniqid(), 'email' => uniqid().'@t.test', 'password' => 'password']);
        $user->tenant_id = $hotel->tenant_id;
        $user->email_verified_at = now();
        $user->save();
        $user->hotels()->attach($hotel->id, ['role' => $role]);

        return [$user, $hotel];
    }

    private function room(Hotel $hotel, string $number = '101', int $price = 30000): Room
    {
        $base = ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id];
        $type = RoomType::create($base + ['name' => 'Standard', 'base_price' => $price]);

        return Room::create($base + ['number' => $number, 'room_type_id' => $type->id, 'status' => 'available']);
    }

    private function reservation(Hotel $hotel, Room $room, string $status = 'confirmed', int $total = 60000, ?string $in = null, ?string $out = null): Reservation
    {
        $base = ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id];
        $guest = Guest::create($base + ['name' => 'Client '.uniqid()]);

        return Reservation::create($base + [
            'code' => 'R-'.strtoupper(substr(uniqid(), -6)), 'guest_id' => $guest->id, 'room_id' => $room->id,
            'check_in' => $in ?? today()->toDateString(), 'check_out' => $out ?? today()->addDays(2)->toDateString(),
            'total_amount' => $total, 'status' => $status,
        ]);
    }

    public function test_every_module_page_renders_for_the_owner(): void
    {
        [$owner, $hotel] = $this->member('owner');
        $room = $this->room($hotel);
        $res = $this->reservation($hotel, $room);
        $this->actingAs($owner);

        foreach (['dashboard', 'reservations.index', 'rooms.index', 'guests.index', 'arrivals.index', 'invoices.index', 'payments.index',
                  'services.index', 'staff.index', 'stock.index', 'expenses.index', 'reports.index', 'settings.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('invoices.show', $res))->assertOk()->assertSee('F-'.$res->code);
        $this->get(route('reports.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_reservation_flow_create_checkin_pay_checkout(): void
    {
        [$owner, $hotel] = $this->member('owner');
        $room = $this->room($hotel, '101', 25000);
        $guest = Guest::create(['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'name' => 'Awa']);
        $this->actingAs($owner);

        Livewire::test(Reservations\Index::class)
            ->call('openCreate')
            ->set('guest_id', $guest->id)
            ->set('check_in', today()->toDateString())
            ->set('check_out', today()->addDays(2)->toDateString())
            ->set('room_id', $room->id)
            ->set('formStatus', 'confirmed')
            ->call('save')
            ->assertHasNoErrors();

        $r = Reservation::firstOrFail();
        $this->assertSame(50000, $r->total_amount); // 2 nuits × 25 000, calculé côté serveur

        Livewire::test(Reservations\Index::class)->call('checkIn', $r->id);
        $this->assertSame('occupied', $room->fresh()->status);
        $this->assertSame('checked_in', $r->fresh()->status);

        Livewire::test(Payments\Index::class)
            ->set('reservation_id', $r->id)->set('amount', 20000)->set('payMethod', 'cash')->call('save')->assertHasNoErrors();
        $this->assertSame(20000, (int) Payment::sum('amount'));

        Livewire::test(Reservations\Index::class)->call('checkOut', $r->id);
        $this->assertSame('available', $room->fresh()->status);
        $this->assertSame('completed', $r->fresh()->status);
    }

    public function test_double_booking_is_refused(): void
    {
        [$owner, $hotel] = $this->member('owner');
        $room = $this->room($hotel);
        $this->reservation($hotel, $room, 'confirmed');
        $guest = Guest::create(['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'name' => 'B']);
        $this->actingAs($owner);

        Livewire::test(Reservations\Index::class)
            ->set('guest_id', $guest->id)->set('check_in', today()->toDateString())->set('check_out', today()->addDay()->toDateString())
            ->set('room_id', $room->id)->call('save')
            ->assertHasErrors(['room_id']);
        $this->assertSame(1, Reservation::count());
    }

    public function test_payment_cannot_exceed_balance(): void
    {
        [$owner, $hotel] = $this->member('owner');
        $r = $this->reservation($hotel, $this->room($hotel), 'confirmed', 40000);
        $this->actingAs($owner);

        Livewire::test(Payments\Index::class)->set('reservation_id', $r->id)->set('amount', 50000)->call('save')->assertHasErrors(['amount']);
        $this->assertSame(0, Payment::count());
    }

    public function test_cannot_act_on_another_hotels_reservation(): void
    {
        [$owner] = $this->member('owner');
        [, $foreign] = $this->member('owner');
        $foreignRes = $this->reservation($foreign, $this->room($foreign));
        $this->actingAs($owner);

        $this->get(route('invoices.show', $foreignRes))->assertNotFound();

        // En production cette exception devient une page 404 ; la réservation d'un autre hôtel reste intacte.
        try {
            Livewire::test(Reservations\Index::class)->call('cancel', $foreignRes->id);
            $this->fail('Reservation d un autre hotel accessible.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $this->assertSame('confirmed', Reservation::withoutGlobalScopes()->find($foreignRes->id)->status);
    }

    public function test_receptionist_cannot_access_finance_or_admin_pages(): void
    {
        [$user, $hotel] = $this->member('receptionist');
        $this->actingAs($user);

        foreach (['invoices.index', 'expenses.index', 'reports.index', 'staff.index', 'settings.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('payments.index'))->assertOk();      // peut encaisser
        $this->get(route('reservations.index'))->assertOk();   // opérations courantes
    }

    public function test_accountant_cannot_modify_operations(): void
    {
        [$user, $hotel] = $this->member('accountant');
        $r = $this->reservation($hotel, $this->room($hotel), 'confirmed');
        $this->actingAs($user);

        Livewire::test(Reservations\Index::class)->call('cancel', $r->id)->assertForbidden();
        $this->assertSame('confirmed', $r->fresh()->status);
    }

    public function test_stock_cannot_go_negative(): void
    {
        [$owner, $hotel] = $this->member('owner');
        $item = StockItem::create(['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'name' => 'Savon', 'quantity' => 5, 'alert_threshold' => 2]);
        $this->actingAs($owner);

        Livewire::test(Stock\Index::class)->call('openMove', $item->id, 'out')->set('moveQty', 9)->call('saveMove')->assertHasErrors(['moveQty']);
        $this->assertSame(5, $item->fresh()->quantity);

        Livewire::test(Stock\Index::class)->call('openMove', $item->id, 'out')->set('moveQty', 3)->call('saveMove')->assertHasNoErrors();
        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_expense_creation_and_staff_isolation(): void
    {
        [$owner, $hotel] = $this->member('owner');
        [$otherOwner] = $this->member('owner');
        $this->actingAs($owner);

        Livewire::test(Expenses\Index::class)
            ->set('label', 'Électricité')->set('expCategory', 'charges')->set('amount', 45000)->set('spent_at', today()->toDateString())
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('expenses', ['hotel_id' => $hotel->id, 'amount' => 45000]);

        // On ne peut pas rattacher à son hôtel un compte appartenant à un autre client SaaS.
        Livewire::test(Staff\Index::class)
            ->set('name', 'Xavier')->set('email', $otherOwner->email)->set('password', 'secret-pass-1')->set('role', 'manager')
            ->call('save')->assertHasErrors(['email']);
        $this->assertFalse($hotel->users()->whereKey($otherOwner->id)->exists());
    }
}
