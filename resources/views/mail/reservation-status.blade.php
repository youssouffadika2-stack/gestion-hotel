<x-mail::message>
@if ($outcome === 'confirmed')
# Votre réservation est confirmée

Bonjour {{ $clientName }},

**{{ $hotel->name }}** a confirmé votre réservation.
@else
# Votre réservation n'a pas pu être retenue

Bonjour {{ $clientName }},

Malheureusement, **{{ $hotel->name }}** ne peut pas honorer votre demande de réservation {{ $reservation->code }}. Nous vous invitons à choisir d'autres dates ou un autre établissement sur {{ config('app.name') }}.
@endif

@if (filled($reservation->hotel_message))
<x-mail::panel>
**Message de {{ $hotel->name }} :**<br>
{{ $reservation->hotel_message }}
</x-mail::panel>
@endif

<x-mail::panel>
**Référence :** {{ $reservation->code }}<br>
**Séjour :** du {{ $reservation->check_in->format('d/m/Y') }} au {{ $reservation->check_out->format('d/m/Y') }}<br>
**Montant :** {{ number_format($reservation->total_amount, 0, ',', ' ') }} FCFA
@if ($hotel->city)<br>**Lieu :** {{ $hotel->city }}@endif
</x-mail::panel>

<x-mail::button :url="$trackUrl">
Voir ma réservation
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
