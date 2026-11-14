<?php

namespace Tests\Feature;

use App\Livewire\Closing;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Payments;
use App\Livewire\Sales;
use App\Livewire\Staff;
use App\Models\DayClosure;
use App\Models\Department;
use App\Models\DepartmentClosure;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HotelProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClosingTest extends TestCase
{
    use RefreshDatabase;

    private function hotelWithDepartments(): Hotel
    {
        $hotel = Hotel::create(['tenant_id' => Tenant::create(['name' => 'T'.uniqid()])->id, 'name' => 'H'.uniqid()]);
        app(HotelProvisioner::class)->createDefaultDepartments($hotel);

        return $hotel;
    }

    private function member(Hotel $hotel, string $role, array $departmentTypes = []): User
    {
        $user = new User(['name' => 'U'.uniqid(), 'email' => uniqid().'@t.test', 'password' => 'password']);
        $user->tenant_id = $hotel->tenant_id;
        $user->email_verified_at = now();
        $user->save();
        $user->hotels()->attach($hotel->id, ['role' => $role]);
        foreach ($departmentTypes as $type) {
            $user->departments()->attach(Department::where('hotel_id', $hotel->id)->where('type', $type)->value('id'));
        }

        return $user;
    }

    private function dept(Hotel $hotel, string $type): Department
    {
        return Department::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('type', $type)->firstOrFail();
    }

    public function test_registration_creates_an_administrator_with_a_hotel_and_default_services(): void
    {
        $this->post('/register', [
            'name' => 'Awa Admin', 'hotel_name' => 'Hôtel Soleil', 'city' => 'Abidjan',
            'email' => 'awa@soleil.test', 'password' => 'password-123', 'password_confirmation' => 'password-123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'awa@soleil.test')->firstOrFail();
        $hotel = Hotel::where('name', 'Hôtel Soleil')->firstOrFail();

        $this->assertSame($hotel->tenant_id, $user->tenant_id);
        $this->assertSame('owner', $user->roleIn($hotel));
        $this->assertEqualsCanonicalizing(['Réception', 'Restaurant', 'Bar', 'Blanchisserie'], Department::withoutGlobalScopes()->where('hotel_id', $hotel->id)->pluck('name')->all());

        // L'inscription est EN ATTENTE : l'espace s'ouvre après activation par l'administrateur de la plateforme.
        $this->assertSame('pending', $hotel->status);
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();

        $hotel->update(['status' => 'active']);
        app()->forgetScopedInstances(); // une vraie requête HTTP repart de zéro
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        // La carte « Point de la journée » se charge en différé (lazy).
        Livewire::test(\App\Livewire\Dashboard\DayPoint::class)->assertSee('Point de la journée')->assertSee('Restaurant');
    }

    public function test_day_cannot_be_closed_until_every_service_has_closed_its_point(): void
    {
        $hotel = $this->hotelWithDepartments();
        $owner = $this->member($hotel, 'owner');
        $this->actingAs($owner);

        $page = Livewire::test(Closing\Index::class);

        // Aucun service n'a fait son point : refus.
        $page->call('closeDay');
        $this->assertSame(0, DayClosure::count());

        // Tous sauf un : toujours refusé.
        foreach (['reception', 'restaurant', 'bar'] as $type) {
            $page->call('closeDepartment', $this->dept($hotel, $type)->id);
        }
        $page->call('closeDay');
        $this->assertSame(0, DayClosure::count());
        $this->assertSame(3, DepartmentClosure::count());

        // Le dernier service clôture : la journée peut l'être.
        $page->call('closeDepartment', $this->dept($hotel, 'laundry')->id);
        $page->call('closeDay');
        $this->assertSame(1, DayClosure::count());
    }

    public function test_inactive_service_does_not_block_the_day(): void
    {
        $hotel = $this->hotelWithDepartments();
        $owner = $this->member($hotel, 'owner');
        $this->dept($hotel, 'laundry')->update(['active' => false]);
        $this->actingAs($owner);

        $page = Livewire::test(Closing\Index::class);
        foreach (['reception', 'restaurant', 'bar'] as $type) {
            $page->call('closeDepartment', $this->dept($hotel, $type)->id);
        }
        $page->call('closeDay');

        $this->assertSame(1, DayClosure::count());
    }

    public function test_closed_service_refuses_new_sales(): void
    {
        $hotel = $this->hotelWithDepartments();
        $owner = $this->member($hotel, 'owner');
        $resto = $this->dept($hotel, 'restaurant');
        $this->actingAs($owner);

        Livewire::test(Sales\Index::class)->call('openCreate', $resto->id)
            ->set('label', 'Plat du jour')->set('quantity', 2)->set('unit_price', 6000)->set('method', 'cash')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(12000, (int) Sale::sum('amount'));

        Livewire::test(Closing\Index::class)->call('closeDepartment', $resto->id);

        Livewire::test(Sales\Index::class)->call('openCreate', $resto->id)
            ->set('label', 'Dessert')->set('quantity', 1)->set('unit_price', 2000)
            ->call('save')->assertHasErrors(['department_id']);
        $this->assertSame(1, Sale::count());
    }

    public function test_staff_can_only_sell_and_close_in_their_own_service(): void
    {
        $hotel = $this->hotelWithDepartments();
        $waiter = $this->member($hotel, 'staff', ['restaurant']);
        $resto = $this->dept($hotel, 'restaurant');
        $bar = $this->dept($hotel, 'bar');
        $this->actingAs($waiter);

        // Vente dans son service : OK
        Livewire::test(Sales\Index::class)->call('openCreate')
            ->set('department_id', $resto->id)->set('label', 'Poulet')->set('quantity', 1)->set('unit_price', 5500)
            ->call('save')->assertHasNoErrors();

        // Vente dans le Bar : interdit
        Livewire::test(Sales\Index::class)->call('openCreate')
            ->set('department_id', $bar->id)->set('label', 'Bière')->set('quantity', 1)->set('unit_price', 1500)
            ->call('save')->assertForbidden();

        Livewire::test(Closing\Index::class)->call('closeDepartment', $bar->id)->assertForbidden();      // pas son service
        Livewire::test(Closing\Index::class)->call('closeDay')->assertForbidden();                       // réservé à l'administrateur / gérant
        Livewire::test(Closing\Index::class)->call('reopenDepartment', $resto->id)->assertForbidden();   // idem

        Livewire::test(Closing\Index::class)->call('closeDepartment', $resto->id);                       // son propre point : OK
        $this->assertSame(1, DepartmentClosure::count());

        // L'employé n'accède pas aux réservations ni aux finances.
        $this->get(route('reservations.index'))->assertForbidden();
        $this->get(route('expenses.index'))->assertForbidden();
        $this->get(route('staff.index'))->assertForbidden();
    }

    public function test_reception_payments_are_refused_once_reception_point_is_closed(): void
    {
        $hotel = $this->hotelWithDepartments();
        $owner = $this->member($hotel, 'owner');
        $base = ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id];
        $type = RoomType::create($base + ['name' => 'Std', 'base_price' => 30000]);
        $room = Room::create($base + ['number' => '1', 'room_type_id' => $type->id, 'status' => 'available']);
        $guest = Guest::create($base + ['name' => 'Client']);
        $res = Reservation::create($base + ['code' => 'R-TEST01', 'guest_id' => $guest->id, 'room_id' => $room->id,
            'check_in' => today(), 'check_out' => today()->addDay(), 'total_amount' => 30000, 'status' => 'confirmed']);
        $this->actingAs($owner);

        Livewire::test(Payments\Index::class)->set('reservation_id', $res->id)->set('amount', 10000)->call('save')->assertHasNoErrors();

        Livewire::test(Closing\Index::class)->call('closeDepartment', $this->dept($hotel, 'reception')->id);

        Livewire::test(Payments\Index::class)->set('reservation_id', $res->id)->set('amount', 5000)->call('save')->assertHasErrors(['reservation_id']);
        $this->assertSame(1, \App\Models\Payment::count());
    }

    public function test_sales_count_in_dashboard_revenue(): void
    {
        $hotel = $this->hotelWithDepartments();
        $owner = $this->member($hotel, 'owner');
        Sale::create(['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'department_id' => $this->dept($hotel, 'bar')->id,
            'label' => 'Bière', 'quantity' => 4, 'unit_price' => 1500, 'amount' => 6000, 'method' => 'cash',
            'sold_at' => now(), 'business_date' => today()->toDateString()]);
        $this->actingAs($owner);

        Livewire::test(DashboardIndex::class)->call('setPeriod', 'today')
            ->assertViewHas('stats', fn ($s) => $s['revenue'] === 6000);
    }

    public function test_staff_assignment_ignores_departments_of_other_hotels(): void
    {
        $hotel = $this->hotelWithDepartments();
        $other = $this->hotelWithDepartments();
        $owner = $this->member($hotel, 'owner');
        $this->actingAs($owner);

        Livewire::test(Staff\Index::class)->call('openCreate')
            ->set('name', 'Nouvel Employé')->set('email', 'new@staff.test')->set('password', 'secret-pass-1')->set('role', 'staff')
            ->set('departmentIds', [$this->dept($hotel, 'bar')->id, $this->dept($other, 'bar')->id])
            ->call('save')->assertHasNoErrors();

        $employee = User::where('email', 'new@staff.test')->firstOrFail();
        $this->assertSame([$this->dept($hotel, 'bar')->id], $employee->departments()->pluck('departments.id')->all());
        $this->assertSame('staff', $employee->roleIn($hotel));
        $this->assertSame($hotel->tenant_id, $employee->tenant_id);
    }

    public function test_every_page_still_renders_for_the_new_pages(): void
    {
        $hotel = $this->hotelWithDepartments();
        $owner = $this->member($hotel, 'owner');
        $this->actingAs($owner);

        foreach (['closing.index', 'sales.index', 'services.index', 'staff.index', 'dashboard'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
