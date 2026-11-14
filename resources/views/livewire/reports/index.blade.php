@php use App\Support\Money; @endphp
<div class="mx-auto max-w-7xl space-y-5">
    <x-ui.page-header title="Rapports et statistiques" subtitle="Performance financière de l'hôtel.">
        <x-slot:actions>
            <div class="inline-flex rounded-lg bg-white p-1 shadow-sm ring-1 ring-slate-200" role="group" aria-label="Période">
                @foreach ($periods as $k => $l)
                    <button type="button" wire:click="$set('period', '{{ $k }}')" aria-pressed="{{ $period === $k ? 'true' : 'false' }}"
                            class="rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $period === $k ? 'bg-navy-900 text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">{{ $l }}</button>
                @endforeach
            </div>
            <a href="{{ route('reports.export', ['periode' => $period]) }}" class="hp-btn-ghost"><x-ui.icon name="receipt" class="size-4" />Exporter (CSV)</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div wire:loading.class="opacity-60" class="grid grid-cols-2 gap-3 transition lg:grid-cols-4">
        <x-ui.stat-card label="Chiffre d'affaires" :value="Money::fcfa($report['totals']['revenue'])" icon="banknote" tone="teal" />
        <x-ui.stat-card label="Dépenses" :value="Money::fcfa($report['totals']['expenses'])" icon="trending-down" tone="rose" />
        <x-ui.stat-card label="Résultat net" :value="Money::fcfa($report['totals']['net'])" icon="trending-up" :tone="$report['totals']['net'] >= 0 ? 'emerald' : 'rose'" />
        <x-ui.stat-card label="Taux d'occupation actuel" :value="$occupancy.'%'" icon="bed-double" tone="navy" />
        <x-ui.stat-card label="Réservations créées" :value="$report['reservations']" icon="calendar-check" tone="amber" />
        <x-ui.stat-card label="Taux d'annulation" :value="$report['cancel_rate'].'%'" icon="x" tone="rose" />
        <x-ui.stat-card label="Montant moyen / réservation" :value="Money::fcfa($report['avg_basket'])" icon="receipt" tone="navy" />
    </div>

    <x-ui.card title="Évolution des recettes">
        @if ($report['totals']['revenue'] > 0)
            <x-ui.chart kind="revenue" :data="$report['revenueChart']" height="h-72" />
        @else
            <x-ui.empty-state icon="bar-chart-3" message="Aucune donnée financière sur cette période." />
        @endif
    </x-ui.card>

    <x-ui.card title="Détail" :padding="false">
        <div class="max-h-96 overflow-auto">
            <table class="min-w-full">
                <thead class="sticky top-0 bg-slate-50"><tr>
                    <th class="hp-th px-5">Période</th><th class="hp-th text-right">Recettes</th><th class="hp-th text-right">Dépenses</th><th class="hp-th px-5 text-right">Net</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($report['rows'] as $row)
                        <tr>
                            <td class="hp-td px-5">{{ $row['label'] }}</td>
                            <td class="hp-td text-right">{{ Money::fcfa($row['revenue']) }}</td>
                            <td class="hp-td text-right">{{ Money::fcfa($row['expenses']) }}</td>
                            <td class="hp-td px-5 text-right font-semibold {{ $row['net'] < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ Money::fcfa($row['net']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
