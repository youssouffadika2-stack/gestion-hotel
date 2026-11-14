@extends('vitrine.layout')
@section('title', 'Suivre ma réservation')

@section('content')
@php
    $labels = \App\Models\Reservation::STATUS_LABELS;
    $tones = ['pending' => 'bg-amber-100 text-amber-800', 'confirmed' => 'bg-emerald-100 text-emerald-800', 'checked_in' => 'bg-sky-100 text-sky-800', 'completed' => 'bg-slate-100 text-slate-700', 'cancelled' => 'bg-rose-100 text-rose-800'];
@endphp

<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-bold text-navy-900">Suivre ma réservation</h1>
    <p class="mt-1 text-slate-600">Saisissez la référence reçue par e-mail (ex. R-AB12CD) et l'adresse e-mail utilisée lors de la réservation.</p>

    <form method="post" action="{{ route('vitrine.track.lookup') }}" class="mt-5 grid gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2">
        @csrf
        <label class="text-sm font-medium">Référence
            <input name="code" value="{{ old('code', $code) }}" required maxlength="20" autocomplete="off" class="hp-input mt-1 uppercase" placeholder="R-XXXXXX">
        </label>
        <label class="text-sm font-medium">E-mail
            <input type="email" name="email" value="{{ old('email', $email) }}" required maxlength="150" class="hp-input mt-1">
        </label>
        <div class="sm:col-span-2"><button class="hp-btn-teal px-6 py-2.5">Rechercher</button></div>
    </form>

    @if ($reservation)
        <section class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">Référence</p>
                    <p class="text-xl font-bold text-navy-900">{{ $reservation->code }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $tones[$reservation->status] ?? 'bg-slate-100' }}">{{ $labels[$reservation->status] ?? $reservation->status }}</span>
            </div>

            <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Hôtel</dt><dd class="font-semibold">{{ $hotel?->name }}@if ($hotel?->city) — {{ $hotel->city }}@endif</dd></div>
                <div><dt class="text-slate-500">Chambre</dt><dd class="font-semibold">{{ $reservation->room?->type?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Arrivée</dt><dd class="font-semibold">{{ $reservation->check_in->format('d/m/Y') }}</dd></div>
                <div><dt class="text-slate-500">Départ</dt><dd class="font-semibold">{{ $reservation->check_out->format('d/m/Y') }}</dd></div>
                <div><dt class="text-slate-500">Montant</dt><dd class="font-semibold">{{ number_format($reservation->total_amount, 0, ',', ' ') }} FCFA</dd></div>
                <div><dt class="text-slate-500">Client</dt><dd class="font-semibold">{{ $reservation->guest?->name }}</dd></div>
            </dl>

            @if ($reservation->guest_message)
                <p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600"><span class="font-semibold">Votre message :</span> {{ $reservation->guest_message }}</p>
            @endif

            @if (filled($reservation->hotel_message))
                <p class="mt-4 rounded-lg bg-teal-50 p-3 text-sm text-teal-900 ring-1 ring-teal-200"><span class="font-semibold">Message de l'hôtel :</span> {{ $reservation->hotel_message }}</p>
            @endif

            <p class="mt-4 text-sm text-slate-600">
                @switch($reservation->status)
                    @case('pending') Votre demande est en attente de confirmation par l'hôtel. Vous recevrez un e-mail dès sa réponse. @break
                    @case('confirmed') L'hôtel a confirmé votre réservation. Présentez cette référence à votre arrivée. @break
                    @case('checked_in') Vous êtes actuellement en séjour. @break
                    @case('completed') Séjour terminé. Merci de votre confiance ! @break
                    @case('cancelled') Cette réservation est annulée. @break
                @endswitch
            </p>

            @if (in_array($reservation->status, ['pending', 'confirmed'], true) && ! $reservation->check_in->isBefore(today()))
                <form method="post" action="{{ route('vitrine.track.cancel') }}" class="mt-5 border-t border-slate-100 pt-4"
                      onsubmit="return confirm('Annuler définitivement cette réservation ?')">
                    @csrf
                    <input type="hidden" name="code" value="{{ $reservation->code }}">
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button class="hp-btn-ghost text-rose-600">Annuler ma réservation</button>
                </form>
            @endif
        </section>
    @endif
</div>
@endsection
