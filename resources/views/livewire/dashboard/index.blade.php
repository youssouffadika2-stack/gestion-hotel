@php
    $fmt = fn (?int $n) => number_format((int) $n, 0, ',', ' ').' FCFA';
    $diff = $stats['reservations_diff'];
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    {{-- En-tête + filtre de période --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-navy-900">Tableau de bord</h1>
            <p class="text-sm text-slate-500">{{ $hotel->name }}@if ($hotel->city) · {{ $hotel->city }}@endif</p>
        </div>

        <div class="inline-flex rounded-lg bg-white p-1 shadow-sm ring-1 ring-slate-200" role="group" aria-label="Période">
            @foreach ($periods as $key => $label)
                <button type="button" wire:click="setPeriod('{{ $key }}')" aria-pressed="{{ $period === $key ? 'true' : 'false' }}"
                        @class([
                            'rounded-md px-3 py-1.5 text-xs font-semibold transition sm:text-sm',
                            'bg-navy-900 text-white shadow' => $period === $key,
                            'text-slate-600 hover:bg-slate-100' => $period !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Actions rapides --}}
    <div class="flex flex-wrap gap-2">
        @foreach ([
            ['Nouvelle réservation', 'calendar-check', 'reservations.index', 'manageOperations', true],
            ['Ajouter un client', 'user-plus', 'guests.index', 'manageOperations', false],
            ['Ajouter une chambre', 'bed-double', 'rooms.index', 'manageOperations', false],
            ['Enregistrer un paiement', 'wallet', 'payments.index', 'recordPayment', false],
            ['Enregistrer une dépense', 'trending-down', 'expenses.index', 'viewFinance', false],
        ] as [$label, $icon, $route, $ability, $primary])
            @can($ability, $hotel)
                <a href="{{ route($route, ['nouveau' => 1]) }}"
                   @class([
                       'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-1',
                       'bg-teal-500 text-white hover:bg-teal-600' => $primary,
                       'bg-white text-navy-900 ring-1 ring-slate-200 hover:bg-slate-50' => ! $primary,
                   ])>
                    <x-ui.icon :name="$icon" class="size-4" />{{ $label }}
                </a>
            @endcan
        @endforeach
    </div>

    {{-- Cartes statistiques --}}
    <div wire:loading.class="opacity-60" wire:target="setPeriod" class="grid grid-cols-2 gap-3 transition md:grid-cols-3 xl:grid-cols-6">
        <x-ui.stat-card label="Chambres disponibles" :value="$stats['rooms_available']" icon="door-open" tone="teal"
                        :hint="$stats['rooms_available_pct'].'% sur '.$stats['rooms_total']" />
        <x-ui.stat-card label="Chambres occupées" :value="$stats['rooms_occupied']" icon="bed-double" tone="navy"
                        :hint="'Occupation '.$stats['rooms_occupied_pct'].'%'" />
        <x-ui.stat-card label="Réservations du jour" :value="$stats['reservations_today']" icon="calendar-check" tone="amber"
                        :trend="$diff" trend-suffix="" hint="vs hier" />
        <x-ui.stat-card label="Clients présents" :value="$stats['guests_present']" icon="users" tone="emerald" hint="actuellement hébergés" />

        @if ($canViewFinance)
            <x-ui.stat-card label="Chiffre d'affaires" :value="$fmt($stats['revenue'])" icon="banknote" tone="teal"
                            :trend="$stats['revenue_variation']" hint="vs période préc." />
            <x-ui.stat-card label="Dépenses" :value="$fmt($stats['expenses'])" icon="trending-down" tone="rose" :hint="$periods[$period]" />
        @endif
    </div>

    {{-- Point de la journée (tous les services) --}}
    @can('viewSales', $hotel)
        <livewire:dashboard.day-point lazy />
    @endcan

    {{-- Graphiques : CA + occupation --}}
    <div class="grid gap-4 lg:grid-cols-3">
        @if ($canViewFinance)
            <x-ui.card title="Évolution du chiffre d'affaires" class="lg:col-span-2">
                @if (array_sum($charts['revenue']['values']) > 0)
                    <x-ui.chart kind="revenue" :data="$charts['revenue']" height="h-72" />
                @else
                    <x-ui.empty-state icon="bar-chart-3" message="Aucun paiement enregistré sur cette période." />
                @endif
            </x-ui.card>
        @endif

        <x-ui.card title="Taux d'occupation" class="{{ $canViewFinance ? '' : 'lg:col-span-3' }}">
            @if (array_sum($charts['occupancy']['values']) > 0)
                <x-ui.chart kind="occupancy" :data="$charts['occupancy']" height="h-72" />
            @else
                <x-ui.empty-state icon="bed-double" message="Aucune chambre enregistrée." />
            @endif
        </x-ui.card>
    </div>

    {{-- Réservations récentes | répartition + activités --}}
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <livewire:dashboard.recent-reservations lazy />
        </div>

        <div class="space-y-4">
            <x-ui.card title="Répartition des réservations">
                @if (array_sum($charts['reservations']['values']) > 0)
                    <x-ui.chart kind="reservations" :data="$charts['reservations']" height="h-60" />
                @else
                    <x-ui.empty-state icon="calendar-check" message="Aucune réservation sur cette période." />
                @endif
            </x-ui.card>

            <livewire:dashboard.recent-activities lazy />
        </div>
    </div>
</div>
