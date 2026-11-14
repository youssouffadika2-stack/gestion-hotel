<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\CurrentHotel;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    /** Facture imprimable. La liaison de modèle passe par le scope d'hôtel : autre hôtel => 404. */
    public function show(Reservation $reservation)
    {
        $hotel = app(CurrentHotel::class)->get();
        abort_unless($hotel && $reservation->hotel_id === $hotel->id, 404);
        Gate::authorize('viewFinance', $hotel);
        abort_if($reservation->status === Reservation::CANCELLED, 404);

        $reservation->load(['guest', 'room.type', 'payments' => fn ($q) => $q->oldest('paid_at')]);

        return view('invoices.show', ['reservation' => $reservation, 'hotel' => $hotel]);
    }
}
