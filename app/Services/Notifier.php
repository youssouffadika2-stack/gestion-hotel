<?php

namespace App\Services;

use App\Mail\HotelAccessMail;
use App\Mail\NewOnlineReservationMail;
use App\Mail\ReservationReceivedMail;
use App\Mail\ReservationStatusMail;
use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi des e-mails automatiques. Un e-mail qui échoue ne doit JAMAIS bloquer l'opération métier
 * (activation, réservation…) : l'erreur est journalisée et la méthode renvoie false.
 */
class Notifier
{
    /** À l'hôtel : activation (event = activated) ou nouveaux accès (event = password_reset). */
    public function hotelAccess(Hotel $hotel, string $email, ?string $password, string $event): bool
    {
        return $this->send($email, new HotelAccessMail($hotel, $email, $password, $event));
    }

    /** Au client : accusé de réception de sa demande. */
    public function reservationReceived(Reservation $reservation, Hotel $hotel, string $clientName, ?string $clientEmail): bool
    {
        return $clientEmail ? $this->send($clientEmail, new ReservationReceivedMail($reservation, $hotel, $clientName)) : false;
    }

    /** Au personnel de l'hôtel (administrateur, gérant, réceptionnistes) : nouvelle demande en ligne. */
    public function newOnlineReservation(Reservation $reservation, Hotel $hotel, string $clientName, ?string $clientPhone, ?string $clientEmail, string $roomLabel): bool
    {
        $recipients = $this->staffEmails($hotel);
        if ($recipients === []) {
            return false;
        }

        return $this->send($recipients, new NewOnlineReservationMail($reservation, $hotel, $clientName, $clientPhone, $clientEmail, $roomLabel));
    }

    /** Au client : l'hôtel a confirmé (confirmed) ou refusé (cancelled) sa réservation. */
    public function reservationStatus(Reservation $reservation, Hotel $hotel, string $clientName, ?string $clientEmail, string $outcome): bool
    {
        return $clientEmail ? $this->send($clientEmail, new ReservationStatusMail($reservation, $hotel, $clientName, $outcome)) : false;
    }

    /** @return array<int,string> */
    public function staffEmails(Hotel $hotel): array
    {
        return $hotel->users()->wherePivotIn('role', ['owner', 'manager', 'receptionist'])
            ->pluck('users.email')->filter()->unique()->values()->all();
    }

    private function send(string|array $to, $mailable): bool
    {
        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
