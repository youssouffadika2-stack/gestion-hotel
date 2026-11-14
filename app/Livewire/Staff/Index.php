<?php

namespace App\Livewire\Staff;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Personnel et accès')]
class Index extends Component
{
    use InteractsWithHotel;

    public const ROLES = [
        'owner' => 'Administrateur (voit tout)',
        'manager' => 'Gérant',
        'accountant' => 'Comptable',
        'receptionist' => 'Réceptionniste',
        'staff' => 'Employé de service',
    ];

    public const ROLE_HELP = [
        'owner' => 'Accès complet, gère le personnel, les accès et rouvre les journées.',
        'manager' => 'Tout sauf le personnel et les paramètres ; clôture la journée.',
        'accountant' => 'Consulte finances, factures, rapports et dépenses ; encaisse.',
        'receptionist' => 'Réservations, clients, chambres, encaissements et point de la Réception.',
        'staff' => 'Vend et fait le point uniquement dans les services qui lui sont affectés.',
    ];

    public bool $showForm = false;
    public ?int $editingUserId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'staff';

    /** @var array<int, int|string> */
    public array $departmentIds = [];

    protected array $validationAttributes = ['name' => 'nom', 'email' => 'e-mail', 'password' => 'mot de passe', 'role' => 'rôle'];

    public function mount(): void
    {
        $this->authorizeHotel('manageHotel');
    }

    public function openCreate(): void
    {
        $this->authorizeHotel('manageHotel');
        $this->resetValidation();
        $this->reset(['editingUserId', 'name', 'email', 'password', 'departmentIds']);
        $this->role = 'staff';
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $hotel = $this->authorizeHotel('manageHotel');
        $user = $hotel->users()->whereKey($userId)->firstOrFail();

        if ($user->id === auth()->id()) {
            $this->toast('Vous ne pouvez pas modifier votre propre accès.', 'error');

            return;
        }

        $this->resetValidation();
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->pivot->role;
        $this->departmentIds = $user->departments()->where('departments.hotel_id', $hotel->id)->pluck('departments.id')->map(fn ($i) => (int) $i)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('manageHotel');
        $editing = $this->editingUserId !== null;

        $rules = [
            'role' => 'required|in:'.implode(',', array_keys(self::ROLES)),
            'departmentIds' => 'array',
            'departmentIds.*' => 'integer',
        ];
        if (! $editing) {
            $rules += [
                'name' => 'required|string|min:2|max:120',
                'email' => 'required|email|max:150',
                'password' => ['required', Password::min(8)],
            ];
        }
        $data = $this->validate($rules);

        // Seuls les services de CET hôtel sont acceptés (les identifiants viennent du navigateur).
        $validDepartments = Department::where('hotel_id', $hotel->id)->whereIn('id', $data['departmentIds'] ?? [])->pluck('id')->all();

        DB::transaction(function () use ($hotel, $data, $editing, $validDepartments) {
            if ($editing) {
                $user = $hotel->users()->whereKey($this->editingUserId)->firstOrFail();
                abort_if($user->id === auth()->id(), 403);
                $hotel->users()->updateExistingPivot($user->id, ['role' => $data['role']]);
            } else {
                $existing = User::where('email', $this->email)->first();

                if ($existing) {
                    // Un compte existant ne peut être rattaché que s'il appartient au même compte client.
                    if ($existing->tenant_id !== $hotel->tenant_id) {
                        throw ValidationException::withMessages(['email' => 'Cette adresse e-mail est déjà utilisée par un autre compte.']);
                    }
                    $existing->hotels()->syncWithoutDetaching([$hotel->id => ['role' => $data['role']]]);
                    $user = $existing;
                } else {
                    $user = new User(['name' => trim($this->name), 'email' => $this->email, 'password' => Hash::make($this->password)]);
                    $user->tenant_id = $hotel->tenant_id; // jamais depuis le navigateur
                    $user->save();
                    $user->hotels()->attach($hotel->id, ['role' => $data['role']]);
                }
            }

            $this->syncDepartments($user, $hotel, $validDepartments);
            $this->logActivity('reservation_updated', ($editing ? "Accès de {$user->name} modifié" : "{$user->name} ajouté au personnel").' ('.self::ROLES[$data['role']].')');
        });

        $this->showForm = false;
        $this->toast($editing ? 'Accès mis à jour.' : 'Membre du personnel ajouté.');
    }

    /** Remplace les services de l'employé POUR CET HÔTEL uniquement (ceux des autres hôtels sont conservés). */
    private function syncDepartments(User $user, Hotel $hotel, array $ids): void
    {
        $user->departments()->detach(Department::where('hotel_id', $hotel->id)->pluck('id')->all());
        $user->departments()->attach($ids);
    }

    public function remove(int $userId): void
    {
        $hotel = $this->authorizeHotel('manageHotel');

        if ($userId === auth()->id()) {
            $this->toast('Vous ne pouvez pas retirer votre propre accès.', 'error');

            return;
        }

        $user = $hotel->users()->whereKey($userId)->first();
        if (! $user) {
            return;
        }

        DB::transaction(function () use ($hotel, $user) {
            $hotel->users()->detach($user->id);
            $user->departments()->detach(Department::where('hotel_id', $hotel->id)->pluck('id')->all());
        });

        $this->toast('Accès retiré.');
    }

    public function render()
    {
        $hotel = $this->authorizeHotel('manageHotel');

        return view('livewire.staff.index', [
            'members' => $hotel->users()->with(['departments' => fn ($q) => $q->where('departments.hotel_id', $hotel->id)])->orderBy('name')->get(),
            'departments' => Department::where('hotel_id', $hotel->id)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'roles' => self::ROLES,
            'roleHelp' => self::ROLE_HELP,
        ]);
    }
}
