@php use App\Support\Money; @endphp
<div class="mx-auto max-w-6xl space-y-5">
    <x-ui.page-header title="Point de la journée" :subtitle="ucfirst(\Carbon\Carbon::parse($date)->translatedFormat('l j F Y'))">
        <x-slot:actions>
            @if ($summary['day'])
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20"><x-ui.icon name="user-check" class="size-4" />Journée clôturée</span>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($yesterdayOpen)
        <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-inset ring-amber-600/20">
            <strong>La journée d'hier n'a pas été clôturée.</strong> Pensez à faire le point avec votre administrateur.
        </div>
    @endif

    @if ($canCloseDay)
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-ui.stat-card label="Recettes du jour" :value="Money::fcfa($summary['revenue'])" icon="banknote" tone="teal" />
            <x-ui.stat-card label="Dépenses du jour" :value="Money::fcfa($summary['expenses'])" icon="trending-down" tone="rose" />
            <x-ui.stat-card label="Résultat du jour" :value="Money::fcfa($summary['revenue'] - $summary['expenses'])" icon="trending-up" :tone="$summary['revenue'] - $summary['expenses'] >= 0 ? 'emerald' : 'rose'" />
            <x-ui.stat-card label="Services clôturés" :value="(count($summary['rows']) - count($summary['pending'])).' / '.count($summary['rows'])" icon="user-check" tone="navy" />
        </div>
    @endif

    {{-- Un bloc par service --}}
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($rows as $row)
            @php
                $d = $row['department']; $t = $row['totals']; $c = $row['closure'];
                $dayClosed = (bool) $summary['day'];
                $gap = $c && $c->cash_counted !== null ? $c->cash_counted - $c->cash_expected : null;
            @endphp
            <x-ui.card :padding="false" wire:key="close-{{ $d->id }}">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-bold text-navy-900">{{ $d->name }}</h2>
                    @if ($c)
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Point fait</span>
                    @else
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">En cours</span>
                    @endif
                </div>

                <div class="space-y-3 px-5 py-4">
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="text-xs text-slate-500">Total {{ $d->isReception() ? '(encaissements séjours + ventes)' : 'des ventes' }}</p>
                            <p class="text-2xl font-bold text-navy-900">{{ Money::fcfa($t['total']) }}</p>
                        </div>
                        <p class="text-xs text-slate-500">{{ $t['operations'] }} opération(s)</p>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        @foreach ($methods as $k => $label)
                            <div class="flex justify-between"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium">{{ Money::fcfa($t['by_method'][$k] ?? 0) }}</dd></div>
                        @endforeach
                    </dl>

                    @if ($c)
                        <div class="rounded-lg bg-slate-50 p-3 text-sm">
                            <p>Clôturé par <strong>{{ $c->closer?->name ?? '—' }}</strong> à {{ $c->closed_at->format('H:i') }}</p>
                            @if ($c->cash_counted !== null)
                                <p class="mt-1">Espèces comptées : <strong>{{ Money::fcfa($c->cash_counted) }}</strong> —
                                    @if ($gap === 0)<span class="font-semibold text-emerald-700">aucun écart</span>
                                    @else<span class="font-semibold {{ $gap < 0 ? 'text-rose-600' : 'text-amber-600' }}">écart de {{ ($gap > 0 ? '+' : '').number_format($gap, 0, ',', ' ') }} FCFA</span>@endif
                                </p>
                            @endif
                            @if ($c->note)<p class="mt-1 text-slate-600">« {{ $c->note }} »</p>@endif
                        </div>
                        @if ($canCloseDay && ! $dayClosed)
                            <button type="button" wire:click="reopenDepartment({{ $d->id }})" wire:confirm="Rouvrir le point de {{ $d->name }} ?" class="hp-btn-ghost text-xs">Rouvrir ce point</button>
                        @endif
                    @elseif ($row['can_close'] && ! $dayClosed)
                        <div class="space-y-2 border-t border-slate-100 pt-3">
                            <x-ui.field label="Espèces comptées en caisse (FCFA)" name="cash.{{ $d->id }}" hint="Facultatif — permet de calculer l'écart avec les espèces attendues ({{ Money::fcfa($t['cash']) }}).">
                                <input type="number" min="0" wire:model="cash.{{ $d->id }}" class="hp-input">
                            </x-ui.field>
                            <x-ui.field label="Remarque (facultatif)" name="notes.{{ $d->id }}">
                                <input type="text" maxlength="500" wire:model="notes.{{ $d->id }}" class="hp-input">
                            </x-ui.field>
                            <button type="button" wire:click="closeDepartment({{ $d->id }})" wire:confirm="Clôturer le point de {{ $d->name }} ? Plus aucune vente ne pourra être ajoutée aujourd'hui." wire:loading.attr="disabled" class="hp-btn-teal w-full">Faire le point de {{ $d->name }}</button>
                        </div>
                    @else
                        <p class="text-xs text-slate-400">Seuls les employés de ce service ou l'administrateur peuvent faire ce point.</p>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <div class="lg:col-span-2"><x-ui.card><x-ui.empty-state icon="clock" message="Vous n'êtes affecté à aucun service. Demandez à votre administrateur de vous en attribuer un." /></x-ui.card></div>
        @endforelse
    </div>

    {{-- Point total (administrateur / gérant) --}}
    @if ($canCloseDay)
        <x-ui.card title="Point total de l'hôtel">
            @if ($summary['day'])
                <div class="space-y-1 text-sm">
                    <p>Journée clôturée par <strong>{{ $summary['day']->closer?->name ?? '—' }}</strong> à {{ $summary['day']->closed_at->format('H:i') }}.</p>
                    <p>Recettes : <strong>{{ Money::fcfa($summary['day']->total_revenue) }}</strong> · Dépenses : <strong>{{ Money::fcfa($summary['day']->total_expenses) }}</strong></p>
                    @if ($summary['day']->note)<p class="text-slate-600">« {{ $summary['day']->note }} »</p>@endif
                </div>
                @if ($isOwner)
                    <button type="button" wire:click="reopenDay" wire:confirm="Rouvrir la journée ? Les services pourront à nouveau vendre." class="hp-btn-ghost mt-3 text-xs">Rouvrir la journée</button>
                @endif
            @else
                @if ($summary['pending'])
                    <div class="mb-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-inset ring-amber-600/20">
                        <strong>Clôture impossible pour le moment.</strong> Il reste à faire le point de :
                        <span class="font-semibold">{{ implode(', ', $summary['pending']) }}</span>.
                    </div>
                @else
                    <p class="mb-3 text-sm text-emerald-700">Tous les services ont fait leur point. Vous pouvez clôturer la journée.</p>
                @endif
                <x-ui.field label="Remarque (facultatif)" name="dayNote" class="mb-3"><input type="text" maxlength="500" wire:model="dayNote" class="hp-input"></x-ui.field>
                <button type="button" wire:click="closeDay" wire:confirm="Clôturer définitivement la journée ?" @disabled(! $summary['all_closed']) wire:loading.attr="disabled"
                        class="hp-btn-primary disabled:bg-slate-300">Clôturer la journée</button>
            @endif
        </x-ui.card>
    @endif
</div>
