<?php

namespace App\Livewire\Topbar;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use Livewire\Component;

class Search extends Component
{
    use InteractsWithHotel;

    public string $q = '';

    public function updatedQ(): void
    {
        $this->q = mb_substr(trim($this->q), 0, 60);
    }

    public function render()
    {
        $reservations = $guests = $rooms = collect();

        if (mb_strlen($this->q) >= 2) {
            $hotel = $this->hotel();
            // Les jokers LIKE saisis par l'utilisateur sont neutralisés.
            $like = '%'.addcslashes($this->q, '%_\\').'%';

            $reservations = Reservation::where('hotel_id', $hotel->id)
                ->where(fn ($w) => $w->where('code', 'like', $like)
                    ->orWhereHas('guest', fn ($g) => $g->where('name', 'like', $like)))
                ->with('guest:id,name')->latest('id')->limit(5)->get(['id', 'code', 'guest_id', 'status']);

            $guests = Guest::where('hotel_id', $hotel->id)
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like))
                ->limit(5)->get(['id', 'name', 'phone']);

            $rooms = Room::where('hotel_id', $hotel->id)->where('number', 'like', $like)->limit(4)->get(['id', 'number', 'status']);
        }

        return view('livewire.topbar.search', compact('reservations', 'guests', 'rooms'));
    }
}
