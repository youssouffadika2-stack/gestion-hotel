<?php

namespace App\Livewire\OnlineRequests;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Reservation;
use App\Services\ReservationService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Boîte de réception des réservations faites par les clients depuis la page publique de l'hôtel. */
#[Title('Demandes en ligne')]
class Index extends Component
{
    use InteractsWithHotel;
    use WithPagination;

    /** new = non lues, pending = en attente de réponse, all = toutes */
    #[Url(as: 'filtre', except: 'new')]
    public string $filter = 'new';

    public function mount(): void
    {
        $this->authorizeHotel('viewReservations');
        $this->filter = in_array($this->filter, ['new', 'pending', 'all'], true) ? $this->filter : 'new';
    }

    public function updatingFilter(): void { $this->resetPage(); }

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatingSearch(): void { $this->resetPage(); }

    // Fenêtre « Confirmer / Refuser » : message facultatif de l'hôtel, envoyé au client par e-mail et visible dans son suivi.
    public bool $showAnswer = false;
    public ?int $answerId = null;
    public string $answerAction = 'confirm';
    public string $answerMessage = '';

    public function openAnswer(int $id, string $action): void
    {
        $this->authorizeHotel('manageOperations');
        abort_unless(in_array($action, ['confirm', 'cancel'], true), 404);
        $this->reset('answerMessage');
        $this->resetErrorBag();
        $this->answerId = $id;
        $this->answerAction = $action;
        $this->showAnswer = true;
    }

    public function submitAnswer(): void
    {
        $this->validate(['answerMessage' => 'nullable|string|max:1000'], [], ['answerMessage' => 'message']);
        $this->answer((int) $this->answerId, $this->answerAction, $this->answerMessage);
        $this->showAnswer = false;
        $this->reset('answerId', 'answerMessage');
    }

    public function confirm(int $id): void { $this->answer($id, 'confirm'); }
    public function decline(int $id): void { $this->answer($id, 'cancel'); }
    public function checkIn(int $id): void { $this->answer($id, 'check_in'); }
    public function checkOut(int $id): void { $this->answer($id, 'check_out'); }

    /** Confirmer ou refuser : le client est prévenu par e-mail par le service. */
    private function answer(int $id, string $action, ?string $message = null): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $result = app(ReservationService::class)->apply($hotel, $id, $action, auth()->id(), $message);
        $notice = '';
        if ($result['ok'] && in_array($action, ['confirm', 'cancel'], true)) {
            $notice = $result['reservation']->guest()->withoutGlobalScopes()->value('email')
                ? ' Le client a été prévenu par e-mail.'
                : " Le client n'a pas d'e-mail : prévenez-le par téléphone.";
        }
        $this->toast($result['message'].$notice, $result['ok'] ? 'success' : 'error');
    }

    public function markRead(int $id): void
    {
        $hotel = $this->authorizeHotel('viewReservations');
        $done = Reservation::where('hotel_id', $hotel->id)->where('source', Reservation::SOURCE_ONLINE)->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
        $this->toast($done ? 'Demande marquée comme lue.' : 'Cette demande est déjà marquée comme lue.');
    }

    public function markUnread(int $id): void
    {
        $hotel = $this->authorizeHotel('viewReservations');
        Reservation::where('hotel_id', $hotel->id)->where('source', Reservation::SOURCE_ONLINE)->whereKey($id)->update(['read_at' => null]);
        $this->toast('Demande remise dans les nouvelles.');
    }

    public function markAllRead(): void
    {
        $hotel = $this->authorizeHotel('viewReservations');
        Reservation::where('hotel_id', $hotel->id)->where('source', Reservation::SOURCE_ONLINE)->whereNull('read_at')->update(['read_at' => now()]);
        $this->toast('Toutes les demandes sont marquées comme lues.');
    }

    public function render()
    {
        $hotel = $this->authorizeHotel('viewReservations');
        $base = fn () => Reservation::where('hotel_id', $hotel->id)->where('source', Reservation::SOURCE_ONLINE);

        $query = $base()->with(['guest:id,name,phone,email', 'room:id,number,room_type_id', 'room.type:id,name']);
        if (($term = trim($this->search)) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q->where('code', 'like', $like)
                ->orWhereHas('guest', fn ($g) => $g->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like)));
        }
        match ($this->filter) {
            'new' => $query->whereNull('read_at'),
            'pending' => $query->where('status', Reservation::PENDING),
            default => null,
        };

        return view('livewire.online-requests.index', [
            'requests' => $query->latest('created_at')->latest('id')->paginate(10),
            'counts' => [
                'new' => $base()->whereNull('read_at')->count(),
                'pending' => $base()->where('status', Reservation::PENDING)->count(),
                'all' => $base()->count(),
            ],
            'canManage' => auth()->user()->can('manageOperations', $hotel),
            'hotel' => $hotel,
        ]);
    }
}
