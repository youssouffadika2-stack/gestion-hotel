<x-mail::message>
# Votre demande est bien reçue

Bonjour {{ $clientName }},

Nous avons transmis votre demande de réservation à **{{ $hotel->name }}**. L'hôtel va la confirmer très prochainement.

<x-mail::panel>
**Référence :** {{ $reservation->code }}<br>
**Séjour :** du {{ $reservation->check_in->format('d/m/Y') }} au {{ $reservation->check_out->format('d/m/Y') }}<br>
**Montant estimé :** {{ number_format($reservation->total_amount, 0, ',', ' ') }} FCFA
</x-mail::panel>

Conservez cette référence : elle vous permet de suivre ou d'annuler votre réservation.

<x-mail::button :url="$trackUrl">
Suivre ma réservation
</x-mail::button>

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
