<?php

namespace Tests\Feature;

use App\Livewire\Platform\Hotels;
use App\Models\Hotel;
use App\Models\User;
use App\Services\CurrentHotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();

        return $user;
    }

    /** Inscription publique d'un hôtel ; retourne [utilisateur, hôtel]. */
    private function register(string $hotelName = 'Hôtel Soleil', string $email = 'awa@soleil.test'): array
    {
        $this->post('/register', [
            'name' => 'Awa Admin', 'hotel_name' => $hotelName, 'city' => 'Bamako',
            'email' => $email, 'password' => 'mot-de-passe-initial', 'password_confirmation' => 'mot-de-passe-initial',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->post('/logout');

        return [User::where('email', $email)->firstOrFail(), Hotel::where('name', $hotelName)->firstOrFail()];
    }

    public function test_registered_hotel_is_pending_and_cannot_use_its_space(): void
    {
        [$owner, $hotel] = $this->register();

        $this->assertSame('pending', $hotel->status);
        $this->actingAs($owner)->get(route('dashboard'))->assertForbidden()->assertSee("Votre inscription est en attente d'activation");
        $this->get(route('reservations.index'))->assertForbidden();
    }

    public function test_platform_admin_activates_and_the_hotel_can_then_log_in(): void
    {
        [$owner, $hotel] = $this->register();
        $this->actingAs($this->platformAdmin());

        Livewire::test(Hotels::class)->call('activate', $hotel->id);
        $this->assertSame('active', $hotel->fresh()->status);
        $this->assertNotNull($hotel->fresh()->activated_at);

        auth()->logout();
        $this->post('/login', ['email' => $owner->email, 'password' => 'mot-de-passe-initial'])->assertRedirect();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_platform_admin_gives_a_new_password_that_works(): void
    {
        [$owner, $hotel] = $this->register();
        $this->actingAs($this->platformAdmin());

        Livewire::test(Hotels::class)
            ->call('activate', $hotel->id)
            ->call('openAccess', $hotel->id)
            ->set('newPassword', 'Acces-Hotel-2026')
            ->call('saveAccess')
            ->assertHasNoErrors()
            ->assertSet('revealedEmail', $owner->email)
            ->assertSet('revealedPassword', 'Acces-Hotel-2026')
            ->assertSet('newPassword', '');

        auth()->logout();
        $this->post('/login', ['email' => $owner->email, 'password' => 'mot-de-passe-initial'])->assertSessionHasErrors();
        $this->post('/login', ['email' => $owner->email, 'password' => 'Acces-Hotel-2026'])->assertRedirect();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_generated_password_is_valid(): void
    {
        $this->actingAs($this->platformAdmin());

        $component = Livewire::test(Hotels::class)->call('generatePassword');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}$/', $component->get('newPassword'));
    }

    public function test_suspended_hotel_loses_access_immediately(): void
    {
        [$owner, $hotel] = $this->register();
        $hotel->update(['status' => 'active']);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();

        $hotel->update(['status' => 'suspended']);
        app()->forgetScopedInstances(); // une vraie requête HTTP repart de zéro ; on le simule dans le test
        $this->get(route('dashboard'))->assertForbidden()->assertSee('Accès suspendu');
        $this->assertNull(app(CurrentHotel::class)->get());
    }

    public function test_only_platform_admins_can_open_the_platform_space(): void
    {
        [$owner, $hotel] = $this->register();
        $hotel->update(['status' => 'active']);

        $this->actingAs($owner)->get('/plateforme/hotels')->assertForbidden();
        $this->flushSession(); // on change d'utilisateur dans le même test
        $this->actingAs($this->platformAdmin())->get('/plateforme/hotels')->assertOk()->assertSee('Hôtels inscrits');
    }

    public function test_hotel_owner_cannot_call_platform_actions(): void
    {
        [$owner, $hotel] = $this->register();
        $this->actingAs($owner);

        // Même en appelant directement les méthodes, un administrateur d'hôtel est refusé.
        Livewire::test(Hotels::class)->assertForbidden();
        $this->assertSame('pending', $hotel->fresh()->status);
    }

    public function test_platform_admin_is_sent_to_the_platform_space_and_sees_no_hotel_data(): void
    {
        $this->register();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('platform.hotels'));
        $this->get(route('reservations.index'))->assertRedirect(route('platform.hotels'));
    }

    public function test_password_can_only_be_set_for_an_owner_of_that_hotel(): void
    {
        [, $hotelA] = $this->register('Hôtel A', 'a@a.test');
        [$ownerB] = $this->register('Hôtel B', 'b@b.test');
        $this->actingAs($this->platformAdmin());
        $before = $ownerB->fresh()->password;

        Livewire::test(Hotels::class)
            ->call('openAccess', $hotelA->id)
            ->set('ownerId', $ownerB->id) // administrateur d'un AUTRE hôtel
            ->set('newPassword', 'Piratage-1234')
            ->call('saveAccess')
            ->assertNotFound();

        $this->assertSame($before, $ownerB->fresh()->password);
    }

    public function test_platform_admin_command_creates_an_admin(): void
    {
        $this->artisan('hotelpro:platform-admin', ['email' => 'root@hotelpro.test', '--password' => 'super-secret-1'])->assertSuccessful();

        $user = User::where('email', 'root@hotelpro.test')->firstOrFail();
        $this->assertTrue((bool) $user->is_platform_admin);
        $this->assertNull($user->tenant_id);
    }
}
