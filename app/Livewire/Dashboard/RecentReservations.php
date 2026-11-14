<?php

namespace App\Livewire\Dashboard;

use App\Models\Reservation;
use App\Services\CurrentHotel;
use Livewire\Component;
use Livewire\WithPagination;

class RecentReservations extends Component
{
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.dashboard.placeholder', ['height' => 'h-72']);
    }

    public function render()
    {
        $hotel = app(CurrentHotel::class)->get();
        abort_unless($hotel, 403);

        $reservations = Reservation::where('hotel_id', $hotel->id)
            ->with(['guest:id,name', 'room:id,number'])
            ->latest('created_at')->latest('id')
            ->paginate(5);

        return view('livewire.dashboard.recent-reservations', [
            'reservations' => $reservations,
            'labels' => Reservation::STATUS_LABELS,
        ]);
    }
}
