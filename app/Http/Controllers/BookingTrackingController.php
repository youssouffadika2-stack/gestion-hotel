<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;

/**
 * Suivi et annulation d'une réservation par le client, sans compte : référence + e-mail.
 * Pages publiques : toutes les requêtes ignorent explicitement les scopes d'hôtel.
 */
class BookingTrackingController extends Controller
{
    public function form(Request $request)
    {
        return view('vitrine.track', ['code' => strtoupper(trim((string) $request->query('code'))), 'reservation' => null, 'hotel' => null, 'email' => '']);
    }

    public function lookup(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:20', 'email' => 'required|email|max:150']);

        $reservation = $this->find($data['code'], $data['email']);
        if (! $reservation) {
            // Même message que le code soit faux ou l'e-mail faux : on ne révèle rien.
            return back()->withInput()->withErrors(['code' => 'Aucune réservation ne correspond à cette référence et à cet e-mail.']);
        }

        return view('vitrine.track', ['code' => $reservation->code, 'reservation' => $reservation, 'hotel' => Hotel::find($reservation->hotel_id), 'email' => $data['email']]);
    }

    public function cancel(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:20', 'email' => 'required|email|max:150']);

        $reservation = $this->find($data['code'], $data['email']);
        abort_unless($reservation, 404);

        $hotel = Hotel::findOrFail($reservation->hotel_id);
        $flash = null;

        if (! in_array($reservation->status, [Reservation::PENDING, Reservation::CONFIRMED], true) || $reservation->check_in->isBefore(today())) {
            $error = 'Cette réservation ne peut plus être annulée en ligne. Contactez directement l\'hôtel.';
        } else {
            $result = app(ReservationService::class)->apply($hotel, $reservation->id, 'cancel', null);
            $error = $result['ok'] ? null : $result['message'];
            $flash = $result['ok'] ? 'Votre réservation a été annulée. L\'hôtel en a été informé.' : null;
        }

        $reservation = $this->find($data['code'], $data['email']);

        if ($flash) {
            session()->now('ok', $flash);
        }

        return view('vitrine.track', ['code' => $reservation->code, 'reservation' => $reservation, 'hotel' => $hotel, 'email' => $data['email']])
            ->withErrors($error ? ['code' => $error] : []);
    }

    private function find(string $code, string $email): ?Reservation
    {
        return Reservation::withoutGlobalScopes()
            ->where('source', Reservation::SOURCE_ONLINE)
            ->where('code', strtoupper(trim($code)))
            ->whereHas('guest', fn ($q) => $q->withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))]))
            ->with([
                'guest' => fn ($q) => $q->withoutGlobalScopes(),
                'room' => fn ($q) => $q->withoutGlobalScopes()->with(['type' => fn ($t) => $t->withoutGlobalScopes()]),
            ])
            ->first();
    }
}
