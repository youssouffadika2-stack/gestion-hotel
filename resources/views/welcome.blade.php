@extends('vitrine.layout')
@section('title', 'Trouvez votre hôtel')

@section('content')
@php $img = fn ($f) => $f ? config('vitrine.admin_url').'/uploads/'.$f : 'https://placehold.co/800x500?text=Hotel'; @endphp

<section class="mb-10 rounded-2xl bg-gradient-to-br from-navy-900 to-teal-600 px-6 py-12 text-center text-white">
    <h1 class="text-3xl font-bold md:text-4xl">Trouvez l'hôtel idéal et réservez en ligne</h1>
    <p class="mt-2 text-slate-100">Découvrez nos hôtels partenaires, consultez leur catalogue et réservez votre séjour.</p>
    <form method="get" class="mx-auto mt-6 flex max-w-lg gap-2">
        <input name="ville" value="{{ $ville }}" placeholder="Ville (ex : Bamako)" class="hp-input flex-1 text-slate-800">
        <button class="hp-btn-teal">Rechercher</button>
    </form>
</section>

<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($hotels as $h)
        <article class="flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-1 hover:shadow-md">
            <img src="{{ $img($h->image) }}" alt="{{ $h->nom }}" class="h-48 w-full object-cover">
            <div class="flex flex-1 flex-col p-4">
                <div class="flex items-center gap-3">
                    @if ($h->logo)<img src="{{ $img($h->logo) }}" alt="Logo {{ $h->nom }}" class="h-10 w-10 rounded-lg bg-white object-contain ring-1 ring-slate-200">@endif
                    <h2 class="text-lg font-semibold">{{ $h->nom }}</h2>
                </div>
                <p class="text-sm"><span class="text-amber-500">{{ str_repeat('★', (int) $h->etoiles) }}</span>
                    <span class="text-slate-500">· {{ $h->ville }}</span></p>
                @if ($h->publicite)
                    <p class="mt-2 italic text-amber-700">« {{ $h->publicite }} »</p>
                @endif
                <p class="mt-2 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($h->description, 110) }}</p>
                <div class="mt-auto flex items-center justify-between pt-4">
                    <span class="text-sm font-bold">{{ $h->prix_min !== null ? 'Dès '.number_format($h->prix_min, 0, ',', ' ').' FCFA / nuit' : 'Bientôt disponible' }}</span>
                    <a href="{{ route('vitrine.show', $h->id) }}" class="hp-btn-primary">Voir &amp; réserver</a>
                </div>
            </div>
        </article>
    @empty
        <p class="col-span-full text-center text-slate-500">Aucun hôtel disponible pour le moment.</p>
    @endforelse
</div>

<section class="mt-12 rounded-xl bg-white p-6 text-center ring-1 ring-slate-200">
    <h2 class="text-xl font-semibold">Vous dirigez un hôtel ?</h2>
    <p class="mt-1 text-slate-500">Faites la promotion de votre établissement ici et recevez des réservations.</p>
    <a href="{{ route('vitrine.register') }}" class="hp-btn-teal mt-4">Inscrire mon hôtel</a>
</section>
@endsection
