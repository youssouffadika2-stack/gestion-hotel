<?php

namespace App\Livewire\Platform;

use App\Models\Hotel;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Espace de l'administrateur général HOTELPRO : activer / suspendre les hôtels inscrits
 * et leur donner leurs accès. N'expose AUCUNE donnée métier d'un hôtel (chambres, clients, finances…).
 */
#[Layout('components.layouts.platform')]
#[Title('Hôtels inscrits')]
class Hotels extends Component
{
    use WithPagination;

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'recherche', except: '')]
    public string $search = '';

    public bool $showAccess = false;
    public ?int $hotelId = null;
    public ?int $ownerId = null;
    public string $newPassword = '';

    /** Envoyer les accès par e-mail à l'administrateur de l'hôtel. */
    public bool $sendEmail = true;

    /** Identifiants à communiquer, affichés une seule fois après l'enregistrement. */
    public ?string $revealedEmail = null;
    public ?string $revealedPassword = null;
    public ?bool $emailSent = null;

    protected array $validationAttributes = ['newPassword' => 'mot de passe', 'ownerId' => 'administrateur'];

    public function mount(): void
    {
        $this->guard();
    }

    /** Chaque action revérifie le droit : le navigateur n'est jamais cru. */
    private function guard(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function activate(int $id): void
    {
        $this->guard();
        $hotel = Hotel::findOrFail($id);
        $wasActive = $hotel->isActive();
        $hotel->update(['status' => Hotel::ACTIVE, 'activated_at' => $hotel->activated_at ?? now()]);

        // E-mail automatique à l'hôtel (lien de connexion). Un échec d'envoi ne bloque pas l'activation.
        $note = '';
        if (! $wasActive && ($owner = $this->owners($hotel)->first())) {
            $sent = app(Notifier::class)->hotelAccess($hotel, $owner->email, null, 'activated');
            $note = $sent ? " E-mail envoyé à {$owner->email}." : " L'e-mail n'a pas pu être envoyé (voir storage/logs/laravel.log).";
        }

        $this->dispatch('toast', message: "« {$hotel->name} » est activé.".$note, type: 'success');
    }

    public function suspend(int $id): void
    {
        $this->guard();
        $hotel = Hotel::findOrFail($id);
        $hotel->update(['status' => Hotel::SUSPENDED]);
        $this->dispatch('toast', message: "« {$hotel->name} » est suspendu : ses utilisateurs n'ont plus accès.", type: 'success');
    }

    /* ---------------- Accès de l'administrateur de l'hôtel ---------------- */

    public function openAccess(int $id): void
    {
        $this->guard();
        $hotel = Hotel::findOrFail($id);
        $owner = $this->owners($hotel)->first();

        $this->resetValidation();
        $this->hotelId = $hotel->id;
        $this->ownerId = $owner?->id;
        $this->newPassword = '';
        $this->sendEmail = true;
        $this->revealedEmail = $this->revealedPassword = null;
        $this->showAccess = true;
    }

    public function generatePassword(): void
    {
        $this->guard();
        // Alphabet sans caractères ambigus (0/O, 1/l/I) pour être facile à dicter.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $this->newPassword = $password;
    }

    public function saveAccess(): void
    {
        $this->guard();

        $this->validate([
            'ownerId' => 'required|integer',
            'newPassword' => 'required|string|min:8|max:100',
        ]);

        $hotel = Hotel::findOrFail($this->hotelId);
        $owner = $this->owners($hotel)->firstWhere('id', $this->ownerId); // doit être administrateur DE CET hôtel
        abort_unless($owner, 404);

        DB::transaction(function () use ($owner) {
            $owner->password = $this->newPassword; // haché automatiquement (cast « hashed »)
            $owner->email_verified_at ??= now();
            $owner->save();
        });

        $this->revealedEmail = $owner->email;
        $this->revealedPassword = $this->newPassword;

        // Envoi des nouveaux accès par e-mail (seulement si l'hôtel est actif : sinon l'e-mail partira à l'activation).
        $this->emailSent = null;
        if ($this->sendEmail && $hotel->isActive()) {
            $this->emailSent = app(Notifier::class)->hotelAccess($hotel, $owner->email, $this->newPassword, 'password_reset');
        }

        $this->newPassword = '';
    }

    /** Administrateurs (rôle owner) d'un hôtel. */
    private function owners(Hotel $hotel)
    {
        return $hotel->users()->wherePivot('role', 'owner')->orderBy('users.name')->get(['users.id', 'users.name', 'users.email']);
    }

    public function closeAccess(): void
    {
        $this->showAccess = false;
        $this->revealedEmail = $this->revealedPassword = null;
    }

    public function render()
    {
        $this->guard();

        $query = Hotel::query()
            ->with(['users' => fn ($q) => $q->wherePivot('role', 'owner')])
            ->withCount('users');

        if (array_key_exists($this->status, Hotel::STATUS_LABELS)) {
            $query->where('status', $this->status);
        }
        if ($term = trim($this->search)) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('city', 'like', $like)
                ->orWhereHas('users', fn ($u) => $u->where('users.email', 'like', $like)->orWhere('users.name', 'like', $like)));
        }

        $counts = Hotel::query()->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');

        return view('livewire.platform.hotels', [
            'hotels' => $query->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'suspended' THEN 1 ELSE 2 END")->latest('id')->paginate(12),
            'counts' => $counts,
            'labels' => Hotel::STATUS_LABELS,
            'owners' => $this->showAccess && $this->hotelId ? $this->owners(Hotel::findOrFail($this->hotelId)) : collect(),
            'accessHotel' => $this->showAccess && $this->hotelId ? Hotel::find($this->hotelId) : null,
        ]);
    }
}
