@extends('vitrine.layout')
@section('title', 'Inscrire mon hôtel')

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold">Inscrire mon hôtel</h1>
    <p class="mb-6 text-slate-500">Après validation par l'administrateur et paiement du forfait publicitaire, votre hôtel apparaîtra sur la page d'accueil.</p>
    <form method="post" action="{{ route('vitrine.register.store') }}" class="grid gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:grid-cols-3">
        @csrf
        <label class="md:col-span-2">Nom de l'hôtel * <input name="nom" value="{{ old('nom') }}" class="hp-input mt-1" required></label>
        <label>Étoiles
            <select name="etoiles" class="hp-input mt-1">@for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}" @selected(old('etoiles', 3) == $i)>{{ $i }} ★</option>@endfor</select></label>
        <label>Ville * <input name="ville" value="{{ old('ville') }}" class="hp-input mt-1" required></label>
        <label class="md:col-span-2">Adresse <input name="adresse" value="{{ old('adresse') }}" class="hp-input mt-1"></label>
        <label class="md:col-span-1">Téléphone * <input name="telephone" value="{{ old('telephone') }}" class="hp-input mt-1" required></label>
        <label class="md:col-span-2">E-mail * <input type="email" name="email" value="{{ old('email') }}" class="hp-input mt-1" required></label>
        <label class="md:col-span-3">Présentation <textarea name="description" rows="4" class="hp-input mt-1">{{ old('description') }}</textarea></label>
        <div class="md:col-span-3"><button class="hp-btn-primary px-6 py-3">Envoyer ma demande</button></div>
    </form>
</div>
@endsection
