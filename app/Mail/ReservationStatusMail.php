<?php

namespace App\Mail;

use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Réponse de l'hôtel au client : réservation confirmée ou refusée. */
class ReservationStatusMail extends Mailable
{
    use Queueable;

    public function __construct(public Reservation $reservation, public Hotel $hotel, public string $clientName, public string $outcome) // confirmed | cancelled
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->outcome === 'confirmed'
            ? "Réservation {$this->reservation->code} confirmée — {$this->hotel->name}"
            : "Réservation {$this->reservation->code} : réponse de {$this->hotel->name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reservation-status', with: ['trackUrl' => route('vitrine.track', ['code' => $this->reservation->code])]);
    }
}
