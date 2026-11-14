<div class="relative ml-auto hidden max-w-xs flex-1 sm:block" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
    <label class="relative block">
        <span class="sr-only">Rechercher</span>
        <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
        <input type="search" wire:model.live.debounce.300ms="q" @focus="open = true" @input="open = true" autocomplete="off"
               placeholder="Rechercher réservation, client, chambre…"
               class="w-full rounded-lg border-0 bg-slate-100 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-teal-500">
    </label>

    @if (mb_strlen($q) >= 2)
        <div x-show="open" x-cloak class="absolute left-0 right-0 z-30 mt-2 max-h-96 overflow-y-auto rounded-xl bg-white py-2 shadow-lg ring-1 ring-slate-200">
            @if ($reservations->isEmpty() && $guests->isEmpty() && $rooms->isEmpty())
                <p class="px-4 py-3 text-sm text-slate-500">Aucun résultat pour « {{ $q }} ».</p>
            @endif

            @if ($reservations->isNotEmpty())
                <p class="px-4 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Réservations</p>
                @foreach ($reservations as $r)
                    <a href="{{ route('reservations.index', ['voir' => $r->id]) }}" class="flex items-center justify-between gap-2 px-4 py-2 text-sm hover:bg-slate-50">
                        <span class="truncate"><span class="font-semibold text-navy-900">{{ $r->code }}</span> · {{ $r->guest?->name }}</span>
                        <x-ui.badge :status="$r->status" />
                    </a>
                @endforeach
            @endif

            @if ($guests->isNotEmpty())
                <p class="px-4 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Clients</p>
                @foreach ($guests as $g)
                    <a href="{{ route('guests.index', ['recherche' => $g->name]) }}" class="block px-4 py-2 text-sm hover:bg-slate-50">
                        {{ $g->name }} <span class="text-xs text-slate-400">{{ $g->phone }}</span>
                    </a>
                @endforeach
            @endif

            @if ($rooms->isNotEmpty())
                <p class="px-4 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Chambres</p>
                @foreach ($rooms as $room)
                    <a href="{{ route('rooms.index', ['recherche' => $room->number]) }}" class="block px-4 py-2 text-sm hover:bg-slate-50">Chambre {{ $room->number }}</a>
                @endforeach
            @endif
        </div>
    @endif
</div>
