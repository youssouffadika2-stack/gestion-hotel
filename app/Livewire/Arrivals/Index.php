<?php

namespace App\Livewire\Arrivals;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Reservation;
use App\Services\ReservationService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Arrivées et départs')]
class Index extends Component
{
    use InteractsWithHotel;

    public function mount(): void
    {
        $this->authorizeHotel('viewReservations');
    }

    public function checkIn(int $id): void { $this->run($id, 'check_in'); }
    public function checkOut(int $id): void { $this->run($id, 'check_out'); }

    private function run(int $id, string $action): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $result = app(ReservationService::class)->apply($hotel, $id, $action, auth()->id());
        $this->toast($result['message'], $result['ok'] ? 'success' : 'error');
    }

    public function render()
    {
        $hotel = $this->hotel();
        $base = fn () => Reservation::where('hotel_id', $hotel->id)->with(['guest:id,name,phone', 'room:id,number']);

        return view('livewire.arrivals.index', [
            // Arrivées attendues aujourd'hui (et retards : arrivée prévue avant aujourd'hui, pas encore enregistrée)
            'arrivals' => $base()->whereIn('status', [Reservation::PENDING, Reservation::CONFIRMED])
                ->whereDate('check_in', '<=', today())->whereDate('check_out', '>', today())->orderBy('check_in')->get(),
            // Départs : clients en séjour dont le départ est aujourd'hui ou dépassé
            'departures' => $base()->where('status', Reservation::CHECKED_IN)
                ->whereDate('check_out', '<=', today())->orderBy('check_out')->get(),
            'upcoming' => $base()->whereIn('status', [Reservation::PENDING, Reservation::CONFIRMED])
                ->whereDate('check_in', '>', today())->whereDate('check_in', '<=', today()->addDays(3))->orderBy('check_in')->get(),
            'inHouse' => $base()->where('status', Reservation::CHECKED_IN)->whereDate('check_out', '>', today())->orderBy('check_out')->get(),
            'canManage' => auth()->user()->can('manageOperations', $hotel),
        ]);
    }
}
