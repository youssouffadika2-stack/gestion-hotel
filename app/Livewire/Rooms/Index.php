<?php

namespace App\Livewire\Rooms;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Chambres')]
class Index extends Component
{
    use InteractsWithHotel;
    use WithPagination;

    public const STATUS_LABELS = [
        Room::AVAILABLE => 'Disponible',
        Room::OCCUPIED => 'Occupée',
        Room::MAINTENANCE => 'Maintenance',
        Room::OUT_OF_SERVICE => 'Hors service',
    ];

    #[Url(as: 'recherche', except: '')]
    public string $search = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $number = '';
    public ?int $room_type_id = null;
    public string $roomStatus = Room::AVAILABLE;

    public bool $showTypes = false;
    public ?int $typeEditingId = null;
    public string $type_name = '';
    public int|string $type_price = '';

    protected array $validationAttributes = [
        'number' => 'numéro', 'room_type_id' => 'type de chambre', 'roomStatus' => 'statut',
        'type_name' => 'nom du type', 'type_price' => 'prix par nuit',
    ];

    public function mount(): void
    {
        $this->authorizeHotel('viewReservations');
        if (request()->boolean('nouveau') && auth()->user()->can('manageOperations', $this->hotel())) {
            $this->openCreate();
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->authorizeHotel('manageOperations');
        $this->resetValidation();
        $this->reset(['editingId', 'number', 'room_type_id']);
        $this->roomStatus = Room::AVAILABLE;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeHotel('manageOperations');
        $room = $this->room($id);
        $this->resetValidation();
        $this->editingId = $room->id;
        $this->number = $room->number;
        $this->room_type_id = $room->room_type_id;
        $this->roomStatus = $room->status;
        $this->showForm = true;
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('manageOperations');

        $data = $this->validate([
            'number' => ['required', 'string', 'max:20', Rule::unique('rooms', 'number')->where('hotel_id', $hotel->id)->ignore($this->editingId)],
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')->where('hotel_id', $hotel->id)],
            'roomStatus' => 'required|in:'.implode(',', array_keys(self::STATUS_LABELS)),
        ]);

        if ($this->editingId) {
            $room = $this->room($this->editingId);
            $hasGuest = Reservation::where('hotel_id', $hotel->id)->where('room_id', $room->id)->where('status', Reservation::CHECKED_IN)->exists();
            if ($hasGuest && $data['roomStatus'] !== Room::OCCUPIED) {
                $this->addError('roomStatus', 'Un client occupe actuellement cette chambre : enregistrez d\'abord son départ.');

                return;
            }
            $room->update(['number' => $data['number'], 'room_type_id' => $data['room_type_id'], 'status' => $data['roomStatus']]);
            $this->logActivity('reservation_updated', "Chambre {$room->number} modifiée");
        } else {
            $room = Room::create([
                'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id,
                'number' => $data['number'], 'room_type_id' => $data['room_type_id'], 'status' => $data['roomStatus'],
            ]);
            $this->logActivity('room_added', "Chambre {$room->number} ajoutée");
        }

        $this->dataChanged();
        $this->showForm = false;
        $this->toast('Chambre enregistrée.');
    }

    public function delete(int $id): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $room = $this->room($id);

        if (Reservation::where('hotel_id', $hotel->id)->where('room_id', $room->id)->exists()) {
            $this->toast('Cette chambre a un historique de réservations : passez-la plutôt « hors service ».', 'error');

            return;
        }

        $room->delete();
        $this->dataChanged();
        $this->toast('Chambre supprimée.');
    }

    /* --------- Types de chambre --------- */

    public function openTypes(): void
    {
        $this->authorizeHotel('manageOperations');
        $this->resetValidation();
        $this->reset(['typeEditingId', 'type_name', 'type_price']);
        $this->showTypes = true;
    }

    public function editType(int $id): void
    {
        $this->authorizeHotel('manageOperations');
        $type = RoomType::where('hotel_id', $this->hotel()->id)->findOrFail($id);
        $this->typeEditingId = $type->id;
        $this->type_name = $type->name;
        $this->type_price = $type->base_price;
    }

    public function saveType(): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $data = $this->validate(['type_name' => 'required|string|max:60', 'type_price' => 'required|integer|min:0|max:100000000']);

        if ($this->typeEditingId) {
            RoomType::where('hotel_id', $hotel->id)->findOrFail($this->typeEditingId)->update(['name' => $data['type_name'], 'base_price' => $data['type_price']]);
        } else {
            RoomType::create(['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'name' => $data['type_name'], 'base_price' => $data['type_price']]);
        }

        $this->reset(['typeEditingId', 'type_name', 'type_price']);
        $this->toast('Type de chambre enregistré.');
    }

    public function deleteType(int $id): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $type = RoomType::where('hotel_id', $hotel->id)->findOrFail($id);

        if (Room::where('hotel_id', $hotel->id)->where('room_type_id', $type->id)->exists()) {
            $this->toast('Ce type est utilisé par des chambres.', 'error');

            return;
        }
        $type->delete();
        $this->toast('Type supprimé.');
    }

    private function room(int $id): Room
    {
        return Room::where('hotel_id', $this->hotel()->id)->findOrFail($id);
    }

    public function render()
    {
        $hotel = $this->hotel();

        $query = Room::where('hotel_id', $hotel->id)->with('type:id,name,base_price');
        if (array_key_exists($this->status, self::STATUS_LABELS)) {
            $query->where('status', $this->status);
        }
        if ($term = trim($this->search)) {
            $query->where('number', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        return view('livewire.rooms.index', [
            'rooms' => $query->orderBy('number')->paginate(12),
            'types' => RoomType::where('hotel_id', $hotel->id)->withCount('rooms')->orderBy('name')->get(),
            'labels' => self::STATUS_LABELS,
            'canManage' => auth()->user()->can('manageOperations', $hotel),
        ]);
    }
}
