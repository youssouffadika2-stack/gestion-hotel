<x-ui.card title="Réservations récentes" :padding="false">
    <x-slot:actions>
        <a href="{{ route('reservations.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:text-teal-800">
            Voir toutes les réservations <x-ui.icon name="arrow-right" class="size-3.5" />
        </a>
    </x-slot:actions>

    @if ($reservations->isEmpty())
        <x-ui.empty-state icon="calendar-check" message="Aucune réservation pour le moment." />
    @else
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-2.5 font-semibold">Code</th>
                        <th class="px-3 py-2.5 font-semibold">Client</th>
                        <th class="px-3 py-2.5 font-semibold">Chambre</th>
                        <th class="px-3 py-2.5 font-semibold">Arrivée</th>
                        <th class="px-3 py-2.5 font-semibold">Départ</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Montant</th>
                        <th class="px-3 py-2.5 font-semibold">Statut</th>
                        <th class="px-5 py-2.5 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($reservations as $r)
                        <tr wire:key="res-{{ $r->id }}" class="hover:bg-slate-50/60">
                            <td class="whitespace-nowrap px-5 py-3 font-semibold text-navy-900">{{ $r->code }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $r->guest?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $r->room?->number ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $r->check_in->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $r->check_out->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-medium">{{ number_format($r->total_amount, 0, ',', ' ') }} FCFA</td>
                            <td class="whitespace-nowrap px-3 py-3"><x-ui.badge :status="$r->status" /></td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('reservations.index', ['voir' => $r->id]) }}" class="hp-icon-btn" title="Voir le détail" aria-label="Voir {{ $r->code }}"><x-ui.icon name="eye" class="size-4" /></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($reservations->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $reservations->links(data: ['scrollTo' => false]) }}</div>
        @endif
    @endif
</x-ui.card>
