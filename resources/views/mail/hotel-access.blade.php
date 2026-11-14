<x-mail::message>
# {{ $event === 'activated' ? 'Votre hôtel est activé' : 'Vos nouveaux accès' }}

Bonjour,

@if ($event === 'activated')
Bonne nouvelle : votre établissement **{{ $hotel->name }}** vient d'être validé et activé par l'administration {{ config('app.name') }}.
Vous pouvez dès maintenant vous connecter à votre espace de gestion : réservations, chambres, clients, ventes, personnel et clôture de la journée.
@else
L'administration {{ config('app.name') }} a mis à jour l'accès à l'espace de gestion de **{{ $hotel->name }}**.
@endif

<x-mail::panel>
**Adresse :** {{ $loginUrl }}<br>
**E-mail :** {{ $email }}<br>
@if ($password)
**Mot de passe provisoire :** {{ $password }}
@else
**Mot de passe :** votre mot de passe habituel (celui choisi à l'inscription ou communiqué par l'administration). En cas d'oubli, utilisez « Mot de passe oublié » sur la page de connexion.
@endif
</x-mail::panel>

<x-mail::button :url="$loginUrl">
Accéder à mon espace
</x-mail::button>

@if ($password)
Pour votre sécurité, **changez ce mot de passe** dès votre première connexion (menu « Mon profil »).
@endif

Les demandes de réservation de vos clients arriveront dans la rubrique **« Demandes en ligne »** de votre espace, et vous serez prévenu par e-mail.

Cordialement,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
