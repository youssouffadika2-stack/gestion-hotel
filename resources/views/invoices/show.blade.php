@php
    use App\Support\Money;
    $nights = max(1, (int) $reservation->check_in->diffInDays($reservation->check_out));
    $paid = (int) $reservation->payments->sum('amount');
    $balance = max(0, $reservation->total_amount - $paid);
    $methods = ['cash' => 'Espèces', 'mobile_money' => 'Mobile Money', 'card' => 'Carte bancaire', 'transfer' => 'Virement'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facture F-{{ $reservation->code }} · {{ $hotel->name }}</title>
    @vite(['resources/css/app.css'])
    <style>@media print { .no-print { display: none !important } body { background: #fff !important } .sheet { box-shadow: none !important } }</style>
</head>
<body class="bg-slate-100 py-6 font-sans text-slate-700 antialiased">
<div class="no-print mx-auto mb-4 flex max-w-3xl justify-end gap-2 px-4">
    <button onclick="window.print()" class="hp-btn-primary">Imprimer / PDF</button>
    <button onclick="window.close()" class="hp-btn-ghost">Fermer</button>
</div>

<main class="sheet mx-auto max-w-3xl rounded-xl bg-white p-8 shadow-sm sm:p-10">
    <header class="flex items-start justify-between border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-xl font-bold text-navy-900">{{ $hotel->name }}</h1>
            @if ($hotel->city)<p class="text-sm text-slate-500">{{ $hotel->city }}</p>@endif
        </div>
        <div class="text-right">
            <p class="text-2xl font-bold tracking-wide text-teal-600">FACTURE</p>
            <p class="text-sm font-semibold text-navy-900">N° F-{{ $reservation->code }}</p>
            <p class="text-xs text-slate-500">Émise le {{ now()->format('d/m/Y') }}</p>
        </div>
    </header>

    <section class="grid grid-cols-2 gap-6 py-6 text-sm">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Facturé à</p>
            <p class="mt-1 font-semibold text-navy-900">{{ $reservation->guest?->name }}</p>
            @if ($reservation->guest?->phone)<p>{{ $reservation->guest->phone }}</p>@endif
            @if ($reservation->guest?->email)<p>{{ $reservation->guest->email }}</p>@endif
        </div>
        <div class="text-right">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Séjour</p>
            <p class="mt-1">Du {{ $reservation->check_in->format('d/m/Y') }} au {{ $reservation->check_out->format('d/m/Y') }}</p>
            <p>{{ $nights }} nuit(s)</p>
        </div>
    </section>

    <table class="w-full text-sm">
        <thead class="border-y border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr><th class="px-3 py-2 text-left">Désignation</th><th class="px-3 py-2 text-right">Nuits</th><th class="px-3 py-2 text-right">Prix unitaire</th><th class="px-3 py-2 text-right">Total</th></tr>
        </thead>
        <tbody>
            <tr class="border-b border-slate-100">
                <td class="px-3 py-3">Chambre {{ $reservation->room?->number }} — {{ $reservation->room?->type?->name }}</td>
                <td class="px-3 py-3 text-right">{{ $nights }}</td>
                <td class="px-3 py-3 text-right">{{ Money::fcfa($reservation->total_amount / $nights) }}</td>
                <td class="px-3 py-3 text-right font-semibold">{{ Money::fcfa($reservation->total_amount) }}</td>
            </tr>
        </tbody>
    </table>

    <section class="ml-auto mt-6 w-full max-w-xs space-y-1.5 text-sm">
        <div class="flex justify-between"><span>Total</span><span class="font-semibold">{{ Money::fcfa($reservation->total_amount) }}</span></div>
        <div class="flex justify-between text-emerald-700"><span>Déjà payé</span><span>- {{ Money::fcfa($paid) }}</span></div>
        <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold {{ $balance ? 'text-rose-600' : 'text-emerald-700' }}"><span>Reste à payer</span><span>{{ Money::fcfa($balance) }}</span></div>
    </section>

    @if ($reservation->payments->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Paiements reçus</h2>
            @foreach ($reservation->payments as $p)
                <p class="flex justify-between border-b border-slate-100 py-1.5 text-sm"><span>{{ $p->paid_at->format('d/m/Y H:i') }} · {{ $methods[$p->method] ?? $p->method }}</span><span>{{ Money::fcfa($p->amount) }}</span></p>
            @endforeach
        </section>
    @endif

    <footer class="mt-10 border-t border-slate-200 pt-4 text-center text-xs text-slate-400">Merci de votre confiance — {{ $hotel->name }}</footer>
</main>
</body>
</html>
