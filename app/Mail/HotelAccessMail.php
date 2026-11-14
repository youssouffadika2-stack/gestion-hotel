<?php

namespace App\Mail;

use App\Models\Hotel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail envoyé à l'hôtel quand l'administration générale l'active (ou lui change son mot de passe). */
class HotelAccessMail extends Mailable
{
    use Queueable;

    public function __construct(
        public Hotel $hotel,
        public string $email,
        public ?string $password,
        public string $event, // activated | password_reset
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->event === 'activated'
            ? "Votre hôtel « {$this->hotel->name} » est activé sur ".config('app.name')
            : 'Vos nouveaux accès '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.hotel-access', with: ['loginUrl' => url('/login')]);
    }
}
