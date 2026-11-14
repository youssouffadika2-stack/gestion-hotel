@php
    $state = $state ?? 'none';
    $messages = [
        'pending' => [
            'clock', "Votre inscription est en attente d'activation",
            "Votre hôtel « {$hotelName} » a bien été enregistré. L'administrateur HOTELPRO doit l'activer et vous communiquer vos accès. Vous pourrez vous connecter à votre espace dès l'activation.",
        ],
        'suspended' => [
            'x', 'Accès suspendu',
            "L'accès de l'hôtel « {$hotelName} » est actuellement suspendu. Contactez l'administrateur HOTELPRO pour le rétablir.",
        ],
        'none' => [
            'building-2', 'Aucun hôtel associé à votre compte',
            "Demandez à l'administrateur de votre établissement de vous donner accès, ou contactez le support HOTELPRO.",
        ],
    ];
    $support = app(\App\Services\VitrineAccess::class)->support();
    $isLinked = auth()->user()?->hotels()->whereNotNull('hotels.vitrine_id')->exists();
    if ($state !== 'none' && $isLinked) {
        $hotelRow = auth()->user()->hotels()->whereNotNull('hotels.vitrine_id')->first();
        $reason = app(\App\Services\VitrineAccess::class)->reasonFor($hotelRow);
        $messages['suspended'] = ['x', 'Accès suspendu', ($reason === 'expired'
            ? "L'abonnement de l'hôtel « {$hotelName} » est arrivé à échéance."
            : "L'accès de l'hôtel « {$hotelName} » a été suspendu.")." Veuillez appeler notre entreprise de gestion de l'application pour le rétablir."];
        $messages['pending'][2] = $reason === 'payment'
            ? "Votre hôtel « {$hotelName} » est enregistré. Son accès sera activé automatiquement dès l'enregistrement de votre paiement : appelez notre entreprise de gestion de l'application."
            : $messages['pending'][2];
    }
    [$icon, $title, $text] = $messages[$state] ?? $messages['none'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4 font-sans antialiased">
    <div class="w-full max-w-md rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
        <span class="mx-auto flex size-12 items-center justify-center rounded-full {{ $state === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-navy-50 text-navy-700' }}">
            <x-ui.icon :name="$icon" class="size-6" />
        </span>
        <h1 class="mt-4 text-lg font-bold text-navy-900">{{ $title }}</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $text }}</p>
        @if ($state !== 'none' && ($support['phone'] || $support['email']))
            <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">
                <div class="font-semibold">{{ $support['name'] }}</div>
                @if ($support['phone'])<a href="tel:{{ preg_replace('/\s+/', '', $support['phone']) }}" class="text-lg font-bold">📞 {{ $support['phone'] }}</a>@endif
                @if ($support['email'])<div>{{ $support['email'] }}</div>@endif
            </div>
        @endif
        @if ($state === 'pending')
            <p class="mt-3 text-xs text-slate-400">Actualisez cette page une fois que vous avez été prévenu de l'activation.</p>
            <a href="{{ url()->current() }}" class="mt-4 inline-block rounded-lg bg-teal-500 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-600">Actualiser</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">Se déconnecter</button>
        </form>
    </div>
</body>
</html>
