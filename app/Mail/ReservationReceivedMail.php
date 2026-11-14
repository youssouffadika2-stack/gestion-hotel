<?php

namespace App\Mail;

use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Accusé de réception envoyé au client après sa demande de réservation en ligne. */
class ReservationReceivedMail extends Mailable
{
    use Queueable;

    public function __construct(public Reservation $reservation, public Hotel $hotel, public string $clientName)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Demande de réservation {$this->reservation->code} reçue — {$this->hotel->name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reservation-received', with: ['trackUrl' => route('vitrine.track', ['code' => $this->reservation->code])]);
    }
}
