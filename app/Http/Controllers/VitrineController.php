<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\DashboardService;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VitrineController extends Controller
{
    private function db()
    {
        return DB::connection('vitrine');
    }

    /** Un hôtel n'est public que s'il est actif ET que sa publicité payée n'est pas expirée. */
    private function advertised(int $id): ?object
    {
        return $this->db()->table('hotels')->where('id', $id)->where('statut', 'actif')
            ->whereDate('pub_fin', '>=', now()->toDateString())->first();
    }

    public function index(Request $request)
    {
        $ville = trim((string) $request->query('ville'));
        $hotels = $this->db()->table('hotels as h')
            ->selectRaw('h.*, (SELECT MIN(prix) FROM catalogues c WHERE c.hotel_id = h.id AND c.disponible = 1) AS prix_min')
            ->where('h.statut', 'actif')->whereDate('h.pub_fin', '>=', now()->toDateString())
            ->when($ville !== '', fn ($q) => $q->where('h.ville', 'like', '%'.$ville.'%'))
            ->orderByDesc('h.etoiles')->orderByDesc('h.id')->get();

        return view('welcome', compact('hotels', 'ville'));
    }

    public function show(int $id)
    {
        $hotel = $this->advertised($id) ?? abort(404);
        $items = $this->db()->table('catalogues')->where('hotel_id', $id)->where('disponible', 1)->orderBy('prix')->get();

        $photos = $this->db()->table('photos')->where('hotel_id', $id)->orderByDesc('id')->get();

        return view('vitrine.show', compact('hotel', 'items', 'photos'));
    }

    public function reserve(Request $request, int $id): RedirectResponse
    {
        $hotel = $this->advertised($id) ?? abort(404);

        // Anti-robots : le champ « website » est caché aux humains. Rempli = robot, on fait semblant d'accepter.
        if ($request->filled('website')) {
            return redirect()->route('vitrine.show', $id)->with('ok', 'Réservation enregistrée.');
        }

        $data = $request->validate([
            'catalogue_id' => ['required', 'integer'],
            'nom' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'telephone' => ['required', 'string', 'max:40'],
            'arrivee' => ['required', 'date', 'after_or_equal:today'],
            'depart' => ['required', 'date', 'after:arrivee'],
            'personnes' => ['required', 'integer', 'min:1', 'max:50'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        $item = $this->db()->table('catalogues')->where('id', $data['catalogue_id'])
            ->where('hotel_id', $hotel->id)->where('disponible', 1)->first();
        if (! $item || $data['personnes'] > $item->capacite) {
            return back()->withInput()->withErrors(['catalogue_id' => 'Offre indisponible ou capacité dépassée.']);
        }
        $nuits = max(1, (int) round((strtotime($data['depart']) - strtotime($data['arrivee'])) / 86400));
        $total = $nuits * (int) $item->prix;

        // L'espace de gestion de l'hôtel (gestion-hotel) reçoit la réservation : une chambre libre du bon type lui est attribuée.
        $space = Hotel::where('vitrine_id', $hotel->id)->first();
        if (! $space || ! $space->isActive()) {
            return back()->withInput()->withErrors(['catalogue_id' => "Cet hôtel n'accepte pas encore les réservations en ligne."]);
        }

        $code = null;
        $localId = null;
        $booking = null;
        $ok = DB::transaction(function () use ($space, $item, $data, $total, &$code, &$localId, &$booking) {
            $type = RoomType::withoutGlobalScopes()->where('hotel_id', $space->id)->where('vitrine_catalogue_id', $item->id)->first();
            $room = $type ? Room::withoutGlobalScopes()->where('hotel_id', $space->id)->where('room_type_id', $type->id)
                ->availableBetween($data['arrivee'], $data['depart'])->orderBy('number')->lockForUpdate()->first() : null;
            if (! $room) {
                return false;
            }

            $guest = Guest::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $space->id, 'phone' => $data['telephone'], 'name' => $data['nom']],
                ['tenant_id' => $space->tenant_id, 'email' => $data['email']]
            );
            if (! $guest->email) {
                $guest->update(['email' => $data['email']]);
            }
            do {
                $code = 'R-'.strtoupper(Str::random(6));
            } while (Reservation::withoutGlobalScopes()->where('hotel_id', $space->id)->where('code', $code)->exists());

            $reservation = Reservation::create([
                'tenant_id' => $space->tenant_id, 'hotel_id' => $space->id, 'code' => $code,
                'guest_id' => $guest->id, 'room_id' => $room->id,
                'check_in' => $data['arrivee'], 'check_out' => $data['depart'],
                'total_amount' => $total, 'status' => Reservation::PENDING,
                'source' => Reservation::SOURCE_ONLINE, 'guest_message' => $data['message'] ?? null, 'guests_count' => $data['personnes'],
            ]);
            ActivityLog::create([
                'tenant_id' => $space->tenant_id, 'hotel_id' => $space->id, 'user_id' => null,
                'type' => 'reservation_created', 'description' => "Réservation en ligne {$code} — {$guest->name} (ch. {$room->number})",
            ]);
            $localId = $reservation->id;
            $booking = [$reservation, $guest, $room, $type];

            return true;
        });

        if (! $ok) {
            return back()->withInput()->withErrors(['catalogue_id' => "Plus de chambre disponible de ce type sur ces dates : choisissez d'autres dates ou une autre offre."]);
        }
        app(DashboardService::class)->flush($space);

        // E-mails : accusé de réception au client, alerte au personnel de l'hôtel (jamais bloquants).
        [$reservation, $guest, $room, $type] = $booking;
        $notifier = app(Notifier::class);
        $notifier->reservationReceived($reservation, $space, $data['nom'], $data['email']);
        $notifier->newOnlineReservation($reservation, $space, $data['nom'], $data['telephone'], $data['email'], "Ch. {$room->number} ({$type?->name})");

        // Copie dans l'administration générale (suivi global)
        $this->db()->table('reservations')->insert([
            'hotel_id' => $hotel->id, 'catalogue_id' => $item->id,
            'client_nom' => $data['nom'], 'client_email' => $data['email'], 'client_telephone' => $data['telephone'],
            'arrivee' => $data['arrivee'], 'depart' => $data['depart'], 'personnes' => $data['personnes'],
            'total' => $total, 'statut' => 'en_attente', 'gestion_reservation_id' => $localId,
        ]);

        return redirect()->route('vitrine.show', $id)
            ->with('track', route('vitrine.track', ['code' => $code]))
            ->with('ok', "Réservation {$code} enregistrée ({$nuits} nuit(s), ".number_format($total, 0, ',', ' ')." FCFA). L'hôtel vous contactera pour la confirmer. Un e-mail de confirmation vous a été envoyé : gardez votre référence pour suivre ou annuler votre réservation.");
    }

    /** Disponibilité en direct d'une offre sur des dates (appelée par la page de l'hôtel). */
    public function availability(Request $request, int $id)
    {
        $hotel = $this->advertised($id) ?? abort(404);

        $v = Validator::make($request->query(), [
            'catalogue_id' => ['required', 'integer'],
            'arrivee' => ['required', 'date', 'after_or_equal:today'],
            'depart' => ['required', 'date', 'after:arrivee'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'message' => "Choisissez une date d'arrivée et de départ valides."]);
        }

        $q = $v->validated();
        $item = $this->db()->table('catalogues')->where('id', $q['catalogue_id'])->where('hotel_id', $hotel->id)->where('disponible', 1)->first();
        $space = Hotel::where('vitrine_id', $hotel->id)->first();
        $type = ($item && $space) ? RoomType::withoutGlobalScopes()->where('hotel_id', $space->id)->where('vitrine_catalogue_id', $item->id)->first() : null;
        if (! $item || ! $type || ! $space->isActive()) {
            return response()->json(['ok' => false, 'message' => 'Offre indisponible.']);
        }

        $free = Room::withoutGlobalScopes()->where('hotel_id', $space->id)->where('room_type_id', $type->id)
            ->availableBetween($q['arrivee'], $q['depart'])->count();
        $nights = max(1, (int) round((strtotime($q['depart']) - strtotime($q['arrivee'])) / 86400));

        return response()->json([
            'ok' => true, 'available' => $free, 'nights' => $nights, 'total' => $nights * (int) $item->prix,
            'capacity' => (int) $item->capacite,
        ]);
    }

    public function registerForm()
    {
        return view('vitrine.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'nom' => ['required', 'string', 'max:150'], 'ville' => ['required', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'], 'telephone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:150'], 'etoiles' => ['required', 'integer', 'between:1,5'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->db()->table('hotels')->insert($d + ['statut' => 'en_attente']);

        return redirect()->route('welcome')->with('ok', "Demande envoyée ! L'administrateur va valider votre hôtel puis vous remettre vos identifiants : connectez-vous ensuite avec « Espace hôtel » (en haut de la page) pour accéder à votre espace de gestion. Votre hôtel apparaîtra sur cette page d'accueil après paiement du forfait publicitaire.");
    }
}
