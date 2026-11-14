<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Réservez votre hôtel') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<header class="bg-navy-900 text-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <a href="{{ route('welcome') }}" class="text-lg font-bold">{{ config('app.name') }}</a>
        <nav class="flex items-center gap-3 text-sm">
            <a href="{{ route('vitrine.track') }}" class="hover:underline">Suivre ma réservation</a>
            <a href="{{ route('vitrine.register') }}" class="hover:underline">Inscrire mon hôtel</a>
            @auth
                <a href="{{ route('dashboard') }}" class="hp-btn-teal">Mon espace</a>
            @else
                <a href="{{ route('login') }}" class="hp-btn-teal">Espace hôtel</a>
            @endauth
        </nav>
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-8">
    @if (session('ok'))
        <div class="mb-6 rounded-lg bg-emerald-100 px-4 py-3 text-emerald-800">
            {{ session('ok') }}
            @if (session('track'))<a href="{{ session('track') }}" class="ml-1 font-semibold underline">Suivre ma réservation</a>@endif
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-red-100 px-4 py-3 text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
<footer class="py-6 text-center text-sm text-slate-500">© {{ date('Y') }} {{ config('app.name') }}</footer>
</body>
</html>
