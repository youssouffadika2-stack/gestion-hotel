@extends('vitrine.layout')
@section('title', $hotel->nom)

@section('content')
@php $img = fn ($f) => $f ? config('vitrine.admin_url').'/uploads/'.$f : 'https://placehold.co/800x500?text=Hotel'; @endphp

<a href="{{ route('welcome') }}" class="text-sm text-teal-700 hover:underline">&larr; Tous les hôtels</a>

<div class="mt-3 grid gap-6 md:grid-cols-2">
    <img src="{{ $img($hotel->image) }}" alt="" class="w-full rounded-xl object-cover">
    <div>
        @if ($hotel->logo)<img src="{{ $img($hotel->logo) }}" alt="Logo {{ $hotel->nom }}" class="mb-3 h-16 object-contain">@endif
        <h1 class="text-2xl font-bold">{{ $hotel->nom }} <span class="text-amber-500">{{ str_repeat('★', (int) $hotel->etoiles) }}</span></h1>
        @if ($hotel->publicite)<p class="mt-2 text-lg italic text-amber-700">« {{ $hotel->publicite }} »</p>@endif
        <p class="mt-3 whitespace-pre-line">{{ $hotel->description }}</p>
        <ul class="mt-4 space-y-1 text-sm text-slate-600">
            <li>📍 {{ trim(($hotel->adresse ? $hotel->adresse.', ' : '').$hotel->ville) }}</li>
            @if ($hotel->telephone)<li>📞 {{ $hotel->telephone }}</li>@endif
            @if ($hotel->email)<li>✉️ {{ $hotel->email }}</li>@endif
        </ul>
    </div>
</div>

@if ($photos->isNotEmpty())
<h2 class="mb-4 mt-10 text-xl font-semibold">Galerie photos</h2>
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @foreach ($photos as $p)
        <figure class="overflow-hidden rounded-lg bg-white ring-1 ring-slate-200">
            <img src="{{ $img($p->fichier) }}" alt="{{ $p->legende }}" loading="lazy" class="h-40 w-full object-cover">
            @if ($p->legende)<figcaption class="px-2 py-1 text-xs text-slate-600">{{ $p->legende }}</figcaption>@endif
        </figure>
    @endforeach
</div>
@endif

<h2 class="mb-4 mt-10 text-xl font-semibold">Catalogue</h2>
<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($items as $c)
        <article class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <img src="{{ $img($c->image) }}" alt="" class="h-44 w-full object-cover">
            <div class="p-4">
                <h3 class="font-semibold">{{ $c->titre }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $c->description }}</p>
                <p class="mt-2 font-bold">{{ number_format($c->prix, 0, ',', ' ') }} FCFA / nuit</p>
                <p class="text-xs text-slate-500">{{ $c->capacite }} pers. max</p>
            </div>
        </article>
    @empty
        <div class="col-span-full rounded-lg bg-amber-50 px-4 py-3 text-amber-800 ring-1 ring-amber-200">
            Les réservations en ligne de cet hôtel ouvrent bientôt. En attendant, contactez-le directement :
            {{ $hotel->telephone }} {{ $hotel->email }}
        </div>
    @endforelse
</div>

@if ($items->isNotEmpty())
<h2 class="mb-4 mt-10 text-xl font-semibold" id="reserver">Réserver</h2>
<form method="post" action="{{ route('vitrine.reserve', $hotel->id) }}" class="grid gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:grid-cols-4">
    @csrf
    <label class="md:col-span-2">Chambre / offre
        <select name="catalogue_id" class="hp-input mt-1" required>
            @foreach ($items as $c)
                <option value="{{ $c->id }}" @selected(old('catalogue_id') == $c->id)>{{ $c->titre }} — {{ number_format($c->prix, 0, ',', ' ') }} FCFA/nuit</option>
            @endforeach
        </select></label>
    <label>Arrivée <input type="date" name="arrivee" value="{{ old('arrivee') }}" min="{{ date('Y-m-d') }}" class="hp-input mt-1" required></label>
    <label>Départ <input type="date" name="depart" value="{{ old('depart') }}" min="{{ date('Y-m-d', strtotime('+1 day')) }}" class="hp-input mt-1" required></label>
    <label>Personnes <input type="number" name="personnes" min="1" max="50" value="{{ old('personnes', 1) }}" class="hp-input mt-1" required></label>
    <label>Nom complet <input name="nom" value="{{ old('nom') }}" class="hp-input mt-1" required></label>
    <label>Téléphone <input name="telephone" value="{{ old('telephone') }}" class="hp-input mt-1" required></label>
    <label>E-mail <input type="email" name="email" value="{{ old('email') }}" class="hp-input mt-1" required></label>
    <label class="md:col-span-4">Message pour l'hôtel (facultatif)
        <textarea name="message" rows="3" maxlength="500" class="hp-input mt-1" placeholder="Heure d'arrivée, lit bébé, demande particulière…">{{ old('message') }}</textarea></label>

    {{-- Piège anti-robots : invisible pour les humains --}}
    <div class="hidden" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <div id="dispo" class="md:col-span-4 hidden rounded-lg px-4 py-3 text-sm font-medium" role="status" aria-live="polite"></div>

    <div class="md:col-span-4"><button id="btn-reserver" class="hp-btn-teal px-6 py-3">Réserver maintenant</button></div>
</form>

<script>
// Disponibilité en direct : se met à jour quand l'offre ou les dates changent.
(function () {
    const form = document.querySelector('form[action="{{ route('vitrine.reserve', $hotel->id) }}"]');
    const box = document.getElementById('dispo');
    const btn = document.getElementById('btn-reserver');
    if (! form || ! box) return;
    const f = (n) => form.elements[n];
    let timer;

    function show(ok, text) {
        box.classList.remove('hidden', 'bg-emerald-100', 'text-emerald-800', 'bg-rose-100', 'text-rose-800');
        box.classList.add(ok ? 'bg-emerald-100' : 'bg-rose-100', ok ? 'text-emerald-800' : 'text-rose-800');
        box.textContent = text;
    }

    async function check() {
        if (! f('arrivee').value || ! f('depart').value || f('depart').value <= f('arrivee').value) { box.classList.add('hidden'); btn.disabled = false; return; }
        const q = new URLSearchParams({ catalogue_id: f('catalogue_id').value, arrivee: f('arrivee').value, depart: f('depart').value });
        try {
            const r = await fetch('{{ route('vitrine.availability', $hotel->id) }}?' + q, { headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            if (! d.ok) { show(false, d.message || 'Vérification impossible.'); btn.disabled = false; return; }
            const total = new Intl.NumberFormat('fr-FR').format(d.total) + ' FCFA';
            if (d.available > 0) { show(true, '✔ Disponible — ' + d.nights + ' nuit(s) : ' + total + ' (' + d.available + ' chambre(s) libre(s))'); btn.disabled = false; }
            else { show(false, '✖ Plus de chambre libre de ce type sur ces dates. Essayez d\'autres dates ou une autre offre.'); btn.disabled = true; }
            f('personnes').max = d.capacity;
        } catch (e) { box.classList.add('hidden'); btn.disabled = false; }
    }

    ['catalogue_id', 'arrivee', 'depart'].forEach((n) => f(n).addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(check, 150); }));
    check();
})();
</script>
@endif
@endsection
