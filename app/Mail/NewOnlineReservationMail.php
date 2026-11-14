<?php

namespace App\Mail;

use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Prévient le personnel de l'hôtel d'une nouvelle demande de réservation faite par un client. */
class NewOnlineReservationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
        public Hotel $hotel,
        public string $clientName,
        public ?string $clientPhone,
        public ?string $clientEmail,
        public string $roomLabel,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nouvelle réservation en ligne {$this->reservation->code} — {$this->clientName}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-online-reservation', with: ['inboxUrl' => route('online.index')]);
    }
}
