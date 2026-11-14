@php use App\Support\Money; @endphp
<div wire:poll.30s>
    <x-ui.card title="Point de la journée">
        <x-slot:actions>
            <a href="{{ route('closing.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:text-teal-800">
                Ouvrir le point <x-ui.icon name="arrow-right" class="size-3.5" />
            </a>
        </x-slot:actions>

        @if ($summary['day'])
            <p class="mb-3 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"><x-ui.icon name="user-check" class="size-4" />Journée clôturée à {{ $summary['day']->closed_at->format('H:i') }}</p>
        @elseif ($canCloseDay && $summary['all_closed'])
            <p class="mb-3 rounded-lg bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-700">Tous les services ont fait leur point : vous pouvez clôturer la journée.</p>
        @elseif ($canCloseDay && $summary['pending'])
            <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">En attente du point de : <strong>{{ implode(', ', $summary['pending']) }}</strong></p>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty-state icon="clock" message="Aucun service à afficher." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($rows as $row)
                    <li wire:key="dp-{{ $row['department']->id }}" class="flex items-center justify-between gap-2 py-2.5 text-sm">
                        <span class="flex items-center gap-2">
                            <span class="size-2 rounded-full {{ $row['closure'] ? 'bg-emerald-500' : 'bg-amber-400' }}" aria-hidden="true"></span>
                            <span class="font-medium text-navy-900">{{ $row['department']->name }}</span>
                            <span class="text-xs text-slate-400">{{ $row['closure'] ? 'point fait' : 'en cours' }}</span>
                        </span>
                        <span class="font-semibold">{{ Money::fcfa($row['totals']['total']) }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($seesAll)
                <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 text-sm font-bold text-navy-900"><span>Total du jour</span><span>{{ Money::fcfa($summary['revenue']) }}</span></div>
            @endif
        @endif
    </x-ui.card>
</div>
