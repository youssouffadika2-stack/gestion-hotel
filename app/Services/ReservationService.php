<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

/**
 * Changements de statut d'une réservation (confirmer, arrivée, départ, annulation).
 * Chaque transition est atomique : verrou de la ligne, statut vérifié, chambre mise à jour, journal écrit.
 *
 * Pour une réservation faite en ligne par un client : le client est prévenu par e-mail
 * (confirmation / refus) et la copie de l'administration générale est mise à jour.
 */
class ReservationService
{
    private const TRANSITIONS = [
        'confirm' => [[Reservation::PENDING], Reservation::CONFIRMED, null, 'reservation_updated', 'confirmée'],
        'cancel' => [[Reservation::PENDING, Reservation::CONFIRMED], Reservation::CANCELLED, null, 'reservation_updated', 'annulée'],
        'check_in' => [[Reservation::PENDING, Reservation::CONFIRMED], Reservation::CHECKED_IN, Room::OCCUPIED, 'guest_arrived', 'enregistrée (arrivée du client)'],
        'check_out' => [[Reservation::CHECKED_IN], Reservation::COMPLETED, Room::AVAILABLE, 'guest_departed', 'terminée (départ du client)'],
    ];

    /**
     * $userId = null signifie « action du client lui-même » (annulation depuis la page de suivi).
     *
     * @return array{ok: bool, message: string, reservation?: Reservation}
     */
    public function apply(Hotel $hotel, int $reservationId, string $action, ?int $userId, ?string $hotelMessage = null): array
    {
        [$from, $to, $roomStatus, $logType, $verb] = self::TRANSITIONS[$action];

        $result = DB::transaction(function () use ($hotel, $reservationId, $action, $from, $to, $roomStatus, $logType, $verb, $userId, $hotelMessage) {
            // withoutGlobalScopes : fonctionne aussi pour le client (page publique) ; le filtre hotel_id reste explicite.
            $r = Reservation::withoutGlobalScopes()->where('hotel_id', $hotel->id)->lockForUpdate()->findOrFail($reservationId);

            if (! in_array($r->status, $from, true)) {
                return ['ok' => false, 'message' => 'Action impossible depuis le statut « '.Reservation::STATUS_LABELS[$r->status].' ».'];
            }

            // Confirmer / enregistrer l'arrivée : la chambre ne doit pas déjà être prise sur ces dates.
            if (in_array($action, ['confirm', 'check_in'], true) && $r->room_id) {
                $conflict = Reservation::withoutGlobalScopes()
                    ->where('hotel_id', $hotel->id)->where('room_id', $r->room_id)->whereKeyNot($r->id)
                    ->whereIn('status', [Reservation::CONFIRMED, Reservation::CHECKED_IN])
                    ->where('check_in', '<', $r->check_out)->where('check_out', '>', $r->check_in)
                    ->first();
                if ($conflict) {
                    return ['ok' => false, 'message' => "Chambre déjà réservée sur ces dates (réservation {$conflict->code}). Modifiez les dates ou la chambre avant de confirmer."];
                }
            }

            $update = ['status' => $to];
            if ($userId !== null && in_array($action, ['confirm', 'cancel'], true) && trim((string) $hotelMessage) !== '') {
                $update['hotel_message'] = mb_substr(trim($hotelMessage), 0, 1000); // réponse visible par le client
            }
            if ($r->isOnline() && $r->read_at === null) {
                $update['read_at'] = now(); // traiter une demande la marque comme lue
            }
            $r->update($update);

            if ($roomStatus && $r->room_id) {
                // On ne touche jamais une chambre en maintenance / hors service.
                Room::withoutGlobalScopes()->where('hotel_id', $hotel->id)->whereKey($r->room_id)
                    ->whereNotIn('status', [Room::MAINTENANCE, Room::OUT_OF_SERVICE])
                    ->update(['status' => $roomStatus]);
            }

            $by = ($userId === null && $action === 'cancel') ? ' par le client' : '';
            ActivityLog::create([
                'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'user_id' => $userId,
                'type' => $logType, 'description' => "Réservation {$r->code} {$verb}{$by}",
            ]);

            return ['ok' => true, 'message' => "Réservation {$r->code} {$verb}.", 'reservation' => $r];
        });

        if ($result['ok']) {
            app(DashboardService::class)->flush($hotel);
            $this->afterChange($hotel, $result['reservation'], $action, $userId);
        }

        return $result;
    }

    /** E-mail au client et synchronisation avec l'administration générale (réservations en ligne uniquement). */
    private function afterChange(Hotel $hotel, Reservation $r, string $action, ?int $userId): void
    {
        if (! $r->isOnline()) {
            return;
        }

        $this->syncVitrine($hotel, $r, $action);

        // Le client est prévenu de la décision de l'hôtel (pas de mail quand c'est lui qui annule).
        if ($userId !== null && in_array($action, ['confirm', 'cancel'], true)) {
            $guest = $r->guest()->withoutGlobalScopes()->first();
            if ($guest) {
                app(Notifier::class)->reservationStatus($r, $hotel, $guest->name, $guest->email, $action === 'confirm' ? 'confirmed' : 'cancelled');
            }
        }
    }

    /** Met à jour la copie de la réservation dans la base de l'administration générale. */
    private function syncVitrine(Hotel $hotel, Reservation $r, string $action): void
    {
        $statut = match ($action) {
            'confirm', 'check_in' => 'confirmee',
            'cancel' => 'annulee',
            default => null,
        };

        // Garde-fou : seuls les hôtels reliés à la vitrine et les réservations en ligne sont concernés.
        if (! $statut || ! $hotel->vitrine_id) {
            return;
        }

        try {
            DB::connection('vitrine')->table('reservations')
                ->where('hotel_id', $hotel->vitrine_id)->where('gestion_reservation_id', $r->id)
                ->update(['statut' => $statut]);
        } catch (\Throwable $e) {
            report($e); // l'administration générale peut être indisponible : on ne bloque pas l'hôtel
        }
    }
}
