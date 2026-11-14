<?php

namespace App\Livewire\Guests;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Guest;
use App\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clients')]
class Index extends Component
{
    use InteractsWithHotel;
    use WithPagination;

    #[Url(as: 'recherche', except: '')]
    public string $search = '';

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $phone = '';
    public string $email = '';

    protected array $validationAttributes = ['name' => 'nom', 'phone' => 'téléphone', 'email' => 'e-mail'];

    public function mount(): void
    {
        $this->authorizeHotel('viewReservations');
        if (request()->boolean('nouveau') && auth()->user()->can('manageOperations', $this->hotel())) {
            $this->openCreate();
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->authorizeHotel('manageOperations');
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'phone', 'email']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeHotel('manageOperations');
        $g = $this->guest($id);
        $this->resetValidation();
        $this->editingId = $g->id;
        $this->name = $g->name;
        $this->phone = (string) $g->phone;
        $this->email = (string) $g->email;
        $this->showForm = true;
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('manageOperations');

        $data = $this->validate([
            'name' => 'required|string|min:2|max:120',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
        ]);
        $data = array_map(fn ($v) => $v === '' ? null : trim($v), $data);

        if ($this->editingId) {
            $this->guest($this->editingId)->update($data);
        } else {
            Guest::create($data + ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id]);
        }

        $this->dataChanged();
        $this->showForm = false;
        $this->toast('Client enregistré.');
    }

    public function delete(int $id): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $guest = $this->guest($id);

        if (Reservation::where('hotel_id', $hotel->id)->where('guest_id', $guest->id)->exists()) {
            $this->toast('Ce client a des réservations : suppression impossible.', 'error');

            return;
        }
        $guest->delete();
        $this->toast('Client supprimé.');
    }

    private function guest(int $id): Guest
    {
        return Guest::where('hotel_id', $this->hotel()->id)->findOrFail($id);
    }

    public function render()
    {
        $hotel = $this->hotel();

        $query = Guest::where('hotel_id', $hotel->id)->withCount('reservations');
        if ($term = trim($this->search)) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
        }

        return view('livewire.guests.index', [
            'guests' => $query->orderBy('name')->paginate(12),
            'canManage' => auth()->user()->can('manageOperations', $hotel),
        ]);
    }
}
