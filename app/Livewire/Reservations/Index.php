<?php

namespace App\Livewire\Reservations;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Réservations')]
class Index extends Component
{
    use InteractsWithHotel;
    use WithPagination;

    #[Url(as: 'recherche', except: '')]
    public string $search = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    public bool $showForm = false;
    public bool $showDetail = false;

    public ?int $editingId = null;
    public ?int $detailId = null;

    public string $guestMode = 'existing'; // existing | new
    public ?int $guest_id = null;
    public string $guest_name = '';
    public string $guest_phone = '';
    public ?int $room_id = null;
    public string $check_in = '';
    public string $check_out = '';
    public string $formStatus = Reservation::PENDING;

    protected array $validationAttributes = [
        'guest_id' => 'client',
        'guest_name' => 'nom du client',
        'guest_phone' => 'téléphone',
        'room_id' => 'chambre',
        'check_in' => "date d'arrivée",
        'check_out' => 'date de départ',
    ];

    public function mount(): void
    {
        $this->authorizeHotel('viewReservations');

        // Liens depuis le tableau de bord / la recherche : ?nouveau=1 ou ?voir=ID
        if (request()->boolean('nouveau') && auth()->user()->can('manageOperations', $this->hotel())) {
            $this->openCreate();
        }
        if ($id = request()->integer('voir')) {
            $this->show($id);
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function updatedStatus(): void
    {
        if (! array_key_exists($this->status, Reservation::STATUS_LABELS)) {
            $this->status = '';
        }
    }

    /* ------------------------------ Formulaire ------------------------------ */

    public function openCreate(): void
    {
        $this->authorizeHotel('manageOperations');
        $this->resetForm();
        $this->check_in = today()->toDateString();
        $this->check_out = today()->addDay()->toDateString();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeHotel('manageOperations');
        $r = $this->findReservation($id);

        if (! in_array($r->status, [Reservation::PENDING, Reservation::CONFIRMED], true)) {
            $this->toast('Seules les réservations en attente ou confirmées peuvent être modifiées.', 'error');

            return;
        }

        $this->resetForm();
        $this->editingId = $r->id;
        $this->guest_id = $r->guest_id;
        $this->room_id = $r->room_id;
        $this->check_in = $r->check_in->toDateString();
        $this->check_out = $r->check_out->toDateString();
        $this->formStatus = $r->status;
        $this->showDetail = false;
        $this->showForm = true;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'guest_id', 'guest_name', 'guest_phone', 'room_id', 'check_in', 'check_out']);
        $this->guestMode = 'existing';
        $this->formStatus = Reservation::PENDING;
    }

    public function updatedCheckIn(): void { $this->room_id = null; }
    public function updatedCheckOut(): void { $this->room_id = null; }

    #[Computed]
    public function nights(): int
    {
        try {
            $in = CarbonImmutable::parse($this->check_in);
            $out = CarbonImmutable::parse($this->check_out);
        } catch (\Throwable) {
            return 0;
        }

        return max(0, (int) $in->diffInDays($out));
    }

    #[Computed]
    public function availableRooms()
    {
        if (! $this->check_in || ! $this->check_out || $this->nights() < 1) {
            return collect();
        }

        return Room::where('hotel_id', $this->hotel()->id)
            ->availableBetween($this->check_in, $this->check_out, $this->editingId)
            ->with('type:id,name,base_price')->orderBy('number')->get();
    }

    #[Computed]
    public function estimatedTotal(): int
    {
        $room = $this->availableRooms()->firstWhere('id', $this->room_id);

        return $room ? ($room->type?->base_price ?? 0) * $this->nights() : 0;
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('manageOperations');

        $rules = [
            'room_id' => 'required|integer',
            'check_in' => ['required', 'date', $this->editingId ? 'date' : 'after_or_equal:today'],
            'check_out' => 'required|date|after:check_in',
            'formStatus' => 'required|in:'.Reservation::PENDING.','.Reservation::CONFIRMED,
        ];
        $rules += $this->guestMode === 'new'
            ? ['guest_name' => 'required|string|min:2|max:120', 'guest_phone' => 'nullable|string|max:30']
            : ['guest_id' => 'required|integer'];

        $this->validate($rules);

        DB::transaction(function () use ($hotel) {
            // Verrou sur la chambre : empêche deux réservations simultanées du même créneau.
            $room = Room::where('hotel_id', $hotel->id)->lockForUpdate()->find($this->room_id);
            $free = $room && Room::where('hotel_id', $hotel->id)->whereKey($room->id)
                ->availableBetween($this->check_in, $this->check_out, $this->editingId)->exists();

            if (! $free) {
                throw ValidationException::withMessages(['room_id' => "Cette chambre n'est pas disponible sur ces dates."]);
            }

            if ($this->guestMode === 'new') {
                $guest = Guest::create([
                    'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id,
                    'name' => trim($this->guest_name), 'phone' => trim($this->guest_phone) ?: null,
                ]);
            } else {
                $guest = Guest::where('hotel_id', $hotel->id)->find($this->guest_id);
                if (! $guest) {
                    throw ValidationException::withMessages(['guest_id' => 'Client introuvable.']);
                }
            }

            $nights = max(1, (int) CarbonImmutable::parse($this->check_in)->diffInDays($this->check_out));
            $total = ($room->type?->base_price ?? 0) * $nights; // calculé côté serveur

            if ($this->editingId) {
                $r = $this->findReservation($this->editingId);
                abort_unless(in_array($r->status, [Reservation::PENDING, Reservation::CONFIRMED], true), 422);
                $r->update([
                    'guest_id' => $guest->id, 'room_id' => $room->id,
                    'check_in' => $this->check_in, 'check_out' => $this->check_out,
                    'total_amount' => $total, 'status' => $this->formStatus,
                ]);
                $this->logActivity('reservation_updated', "Réservation {$r->code} modifiée");
            } else {
                $r = Reservation::create([
                    'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id,
                    'code' => $this->newCode($hotel->id),
                    'guest_id' => $guest->id, 'room_id' => $room->id,
                    'check_in' => $this->check_in, 'check_out' => $this->check_out,
                    'total_amount' => $total, 'status' => $this->formStatus,
                    'created_by' => auth()->id(),
                ]);
                $this->logActivity('reservation_created', "Nouvelle réservation {$r->code} — {$guest->name} (ch. {$room->number})");
            }
        });

        $this->dataChanged();
        $this->showForm = false;
        $this->toast($this->editingId ? 'Réservation modifiée.' : 'Réservation enregistrée.');
        $this->resetForm();
    }

    private function newCode(int $hotelId): string
    {
        do {
            $code = 'R-'.strtoupper(Str::random(6));
        } while (Reservation::where('hotel_id', $hotelId)->where('code', $code)->exists());

        return $code;
    }

    /* ------------------------------ Actions de statut ------------------------------ */

    public function confirm(int $id): void { $this->transition($id, 'confirm'); }
    public function cancel(int $id): void { $this->transition($id, 'cancel'); }
    public function checkIn(int $id): void { $this->transition($id, 'check_in'); }
    public function checkOut(int $id): void { $this->transition($id, 'check_out'); }

    private function transition(int $id, string $action): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $result = app(ReservationService::class)->apply($hotel, $id, $action, auth()->id());
        $this->toast($result['message'], $result['ok'] ? 'success' : 'error');
    }

    /* ------------------------------ Détail ------------------------------ */

    public function show(int $id): void
    {
        $this->findReservation($id); // 404 si l'id appartient à un autre hôtel
        $this->detailId = $id;
        $this->showDetail = true;
    }

    private function findReservation(int $id): Reservation
    {
        return Reservation::where('hotel_id', $this->hotel()->id)->findOrFail($id);
    }

    public function render()
    {
        $hotel = $this->hotel();

        $query = Reservation::where('hotel_id', $hotel->id)->with(['guest:id,name,phone', 'room:id,number'])
            ->withSum('payments as paid', 'amount');

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }
        if ($term = trim($this->search)) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('code', 'like', $like)
                ->orWhereHas('guest', fn ($g) => $g->where('name', 'like', $like)->orWhere('phone', 'like', $like)));
        }

        $detail = $this->showDetail && $this->detailId
            ? Reservation::where('hotel_id', $hotel->id)->with(['guest', 'room.type', 'payments' => fn ($q) => $q->latest('paid_at')])->withSum('payments as paid', 'amount')->find($this->detailId)
            : null;

        return view('livewire.reservations.index', [
            'reservations' => $query->latest('created_at')->latest('id')->paginate(10),
            'guests' => $this->showForm ? Guest::where('hotel_id', $hotel->id)->orderBy('name')->limit(300)->get(['id', 'name', 'phone']) : collect(),
            'detail' => $detail,
            'labels' => Reservation::STATUS_LABELS,
            'canManage' => auth()->user()->can('manageOperations', $hotel),
            'canPay' => auth()->user()->can('recordPayment', $hotel),
        ]);
    }
}
