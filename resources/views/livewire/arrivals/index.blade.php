@php
    $sections = [
        ['Arrivées du jour', 'user-check', $arrivals, 'checkIn', "Enregistrer l'arrivée", 'Aucune arrivée attendue aujourd\'hui.'],
        ['Départs du jour', 'log-out', $departures, 'checkOut', 'Enregistrer le départ', 'Aucun départ à enregistrer.'],
    ];
@endphp
<div class="mx-auto max-w-7xl">
    <x-ui.page-header title="Arrivées et départs" :subtitle="ucfirst(now()->translatedFormat('l j F Y'))" />

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($sections as [$title, $icon, $items, $method, $btn, $empty])
            <x-ui.card :padding="false">
                <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3.5">
                    <x-ui.icon :name="$icon" class="size-4 text-teal-600" />
                    <h2 class="text-sm font-semibold text-navy-900">{{ $title }}</h2>
                    <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $items->count() }}</span>
                </div>
                @forelse ($items as $r)
                    <div wire:key="{{ $method }}-{{ $r->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-navy-900">{{ $r->guest?->name }} <span class="font-normal text-slate-400">· {{ $r->code }}</span></p>
                            <p class="text-xs text-slate-500">
                                Chambre {{ $r->room?->number ?? '—' }} · {{ $r->check_in->format('d/m') }} → {{ $r->check_out->format('d/m') }}
                                @if ($method === 'checkIn' && $r->check_in->isPast() && ! $r->check_in->isToday()) <span class="font-semibold text-rose-600">· en retard</span> @endif
                                @if ($method === 'checkOut' && $r->check_out->isPast() && ! $r->check_out->isToday()) <span class="font-semibold text-rose-600">· dépassé</span> @endif
                            </p>
                        </div>
                        @if ($canManage)
                            <button type="button" wire:click="{{ $method }}({{ $r->id }})" wire:loading.attr="disabled" class="hp-btn-teal text-xs">{{ $btn }}</button>
                        @endif
                    </div>
                @empty
                    <x-ui.empty-state :icon="$icon" :message="$empty" />
                @endforelse
            </x-ui.card>
        @endforeach

        <x-ui.card title="Prochaines arrivées (3 jours)">
            @forelse ($upcoming as $r)
                <p class="flex justify-between border-b border-slate-100 py-2 text-sm last:border-0"><span>{{ $r->guest?->name }} · Ch. {{ $r->room?->number ?? '—' }}</span><span class="text-slate-500">{{ $r->check_in->translatedFormat('D d/m') }}</span></p>
            @empty
                <x-ui.empty-state icon="calendar" message="Aucune arrivée prévue prochainement." />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Clients actuellement en séjour">
            @forelse ($inHouse as $r)
                <p class="flex justify-between border-b border-slate-100 py-2 text-sm last:border-0"><span>{{ $r->guest?->name }} · Ch. {{ $r->room?->number ?? '—' }}</span><span class="text-slate-500">jusqu'au {{ $r->check_out->format('d/m') }}</span></p>
            @empty
                <x-ui.empty-state icon="users" message="Aucun client en séjour." />
            @endforelse
        </x-ui.card>
    </div>
</div>
