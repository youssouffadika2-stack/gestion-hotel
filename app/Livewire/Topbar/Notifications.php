<?php

namespace App\Livewire\Topbar;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Reservation;
use App\Models\StockItem;
use Livewire\Component;

class Notifications extends Component
{
    use InteractsWithHotel;

    public function render()
    {
        $hotel = $this->hotel();

        $online = Reservation::where('hotel_id', $hotel->id)->where('source', Reservation::SOURCE_ONLINE)->whereNull('read_at')->count();
        $pending = Reservation::where('hotel_id', $hotel->id)->where('status', Reservation::PENDING)
            ->where(fn ($q) => $q->where('source', '!=', Reservation::SOURCE_ONLINE)->orWhereNotNull('read_at'))->count();
        $arrivals = Reservation::where('hotel_id', $hotel->id)->where('status', Reservation::CONFIRMED)
            ->whereDate('check_in', today())->count();
        $departures = Reservation::where('hotel_id', $hotel->id)->where('status', Reservation::CHECKED_IN)
            ->whereDate('check_out', '<=', today())->count();
        $lowStock = StockItem::where('hotel_id', $hotel->id)->whereColumn('quantity', '<=', 'alert_threshold')->count();

        return view('livewire.topbar.notifications', [
            'items' => array_values(array_filter([
                $online ? ['icon' => 'mail', 'text' => "{$online} nouvelle(s) demande(s) de réservation en ligne", 'url' => route('online.index')] : null,
                $pending ? ['icon' => 'clock', 'text' => "{$pending} réservation(s) en attente de confirmation", 'url' => route('reservations.index', ['statut' => 'pending'])] : null,
                $arrivals ? ['icon' => 'user-check', 'text' => "{$arrivals} arrivée(s) attendue(s) aujourd'hui", 'url' => route('arrivals.index')] : null,
                $departures ? ['icon' => 'log-out', 'text' => "{$departures} départ(s) à enregistrer", 'url' => route('arrivals.index')] : null,
                $lowStock ? ['icon' => 'package', 'text' => "{$lowStock} article(s) en stock bas", 'url' => route('stock.index')] : null,
            ])),
            'count' => $online + $pending + $arrivals + $departures + $lowStock,
        ]);
    }
}
