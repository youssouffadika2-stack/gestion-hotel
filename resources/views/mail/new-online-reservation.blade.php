<x-mail::message>
# Nouvelle demande de réservation en ligne

Un client vient de réserver sur la page publique de **{{ $hotel->name }}**.

<x-mail::panel>
**Référence :** {{ $reservation->code }}<br>
**Client :** {{ $clientName }}<br>
@if ($clientPhone)**Téléphone :** {{ $clientPhone }}<br>@endif
@if ($clientEmail)**E-mail :** {{ $clientEmail }}<br>@endif
**Chambre :** {{ $roomLabel }}<br>
**Séjour :** du {{ $reservation->check_in->format('d/m/Y') }} au {{ $reservation->check_out->format('d/m/Y') }}@if ($reservation->guests_count) ({{ $reservation->guests_count }} personne(s))@endif<br>
**Montant :** {{ number_format($reservation->total_amount, 0, ',', ' ') }} FCFA
</x-mail::panel>

@if ($reservation->guest_message)
**Message du client :**

> {{ $reservation->guest_message }}
@endif

<x-mail::button :url="$inboxUrl">
Voir la demande et répondre
</x-mail::button>

La réservation est **en attente** : confirmez ou refusez-la depuis la rubrique « Demandes en ligne ».
</x-mail::message>
