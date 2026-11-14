@php
    use App\Support\Money;
    $tones = ['available' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'occupied' => 'bg-sky-50 text-sky-700 ring-sky-600/20', 'maintenance' => 'bg-amber-50 text-amber-700 ring-amber-600/20', 'out_of_service' => 'bg-rose-50 text-rose-700 ring-rose-600/20'];
@endphp
<div class="mx-auto max-w-7xl">
    <x-ui.page-header title="Chambres" subtitle="État et tarifs de vos chambres.">
        <x-slot:actions>
            @if ($canManage)
                <button type="button" wire:click="openTypes" class="hp-btn-ghost"><x-ui.icon name="settings" class="size-4" />Types et tarifs</button>
                <button type="button" wire:click="openCreate" class="hp-btn-teal"><x-ui.icon name="plus" class="size-4" />Ajouter une chambre</button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Numéro de chambre…" class="hp-input pl-9">
            </div>
            <select wire:model.live="status" class="hp-input sm:w-52" aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                @foreach ($labels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
        </div>

        @if ($rooms->isEmpty())
            <x-ui.empty-state icon="bed-double" message="Aucune chambre. Ajoutez votre première chambre pour commencer." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50"><tr>
                        <th class="hp-th px-5">Numéro</th><th class="hp-th">Type</th><th class="hp-th text-right">Prix / nuit</th><th class="hp-th">Statut</th><th class="hp-th px-5 text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rooms as $room)
                            <tr wire:key="room-{{ $room->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5 font-semibold text-navy-900">{{ $room->number }}</td>
                                <td class="hp-td">{{ $room->type?->name ?? '—' }}</td>
                                <td class="hp-td text-right">{{ Money::fcfa($room->type?->base_price) }}</td>
                                <td class="hp-td"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $tones[$room->status] ?? '' }}">{{ $labels[$room->status] ?? $room->status }}</span></td>
                                <td class="hp-td px-5 text-right">
                                    @if ($canManage)
                                        <button type="button" wire:click="edit({{ $room->id }})" class="hp-icon-btn" title="Modifier" aria-label="Modifier la chambre {{ $room->number }}"><x-ui.icon name="settings" class="size-4" /></button>
                                        <button type="button" wire:click="delete({{ $room->id }})" wire:confirm="Supprimer la chambre {{ $room->number }} ?" class="hp-icon-btn hover:text-rose-600" title="Supprimer" aria-label="Supprimer la chambre {{ $room->number }}"><x-ui.icon name="x" class="size-4" /></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rooms->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $rooms->links() }}</div>@endif
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Modifier la chambre' : 'Ajouter une chambre'" max-width="md">
        <form wire:submit="save" id="room-form" class="space-y-4">
            <x-ui.field label="Numéro" name="number"><input type="text" wire:model="number" class="hp-input" placeholder="ex. 101" autocomplete="off"></x-ui.field>
            <x-ui.field label="Type de chambre" name="room_type_id" :hint="$types->isEmpty() ? 'Créez d\'abord un type via « Types et tarifs ».' : null">
                <select wire:model="room_type_id" class="hp-input">
                    <option value="">— Choisir —</option>
                    @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ Money::fcfa($t->base_price) }})</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Statut" name="roomStatus">
                <select wire:model="roomStatus" class="hp-input">
                    @foreach ($labels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </x-ui.field>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="room-form" class="hp-btn-teal">Enregistrer</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showTypes" title="Types de chambre et tarifs" max-width="lg">
        <form wire:submit="saveType" class="grid gap-3 sm:grid-cols-5">
            <x-ui.field label="Nom du type" name="type_name" class="sm:col-span-2"><input type="text" wire:model="type_name" class="hp-input" placeholder="Standard"></x-ui.field>
            <x-ui.field label="Prix par nuit (FCFA)" name="type_price" class="sm:col-span-2"><input type="number" min="0" step="500" wire:model="type_price" class="hp-input"></x-ui.field>
            <div class="flex items-end"><button type="submit" class="hp-btn-teal w-full">{{ $typeEditingId ? 'Mettre à jour' : 'Ajouter' }}</button></div>
        </form>
        <ul class="mt-4 divide-y divide-slate-100">
            @forelse ($types as $t)
                <li wire:key="type-{{ $t->id }}" class="flex items-center justify-between py-2.5 text-sm">
                    <span><span class="font-semibold text-navy-900">{{ $t->name }}</span> · {{ Money::fcfa($t->base_price) }} <span class="text-xs text-slate-400">({{ $t->rooms_count }} chambre(s))</span></span>
                    <span>
                        <button type="button" wire:click="editType({{ $t->id }})" class="hp-icon-btn" aria-label="Modifier {{ $t->name }}"><x-ui.icon name="settings" class="size-4" /></button>
                        <button type="button" wire:click="deleteType({{ $t->id }})" wire:confirm="Supprimer ce type ?" class="hp-icon-btn hover:text-rose-600" aria-label="Supprimer {{ $t->name }}"><x-ui.icon name="x" class="size-4" /></button>
                    </span>
                </li>
            @empty
                <li><x-ui.empty-state icon="bed-double" message="Aucun type de chambre." /></li>
            @endforelse
        </ul>
    </x-ui.modal>
</div>
