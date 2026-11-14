@php use App\Support\Money; @endphp
<div class="mx-auto max-w-7xl">
    <x-ui.page-header title="Ventes des services" subtitle="Restaurant, bar, blanchisserie… Enregistrez chaque vente au fil de la journée.">
        <x-slot:actions>
            <a href="{{ route('closing.index') }}" class="hp-btn-ghost"><x-ui.icon name="clock" class="size-4" />Point du jour</a>
            @if ($operable->isNotEmpty())
                <button type="button" wire:click="openCreate" class="hp-btn-teal"><x-ui.icon name="plus" class="size-4" />Nouvelle vente</button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ($visible as $d)
            @php $t = $totals->get($d->id); $locked = in_array($d->id, $lockedIds); @endphp
            <button type="button" wire:click="$set('departmentFilter', '{{ $departmentFilter == $d->id ? '' : $d->id }}')" wire:key="chip-{{ $d->id }}"
                    class="rounded-xl bg-white p-4 text-left shadow-sm ring-1 transition hover:shadow-md {{ $departmentFilter == $d->id ? 'ring-2 ring-teal-500' : 'ring-slate-200/70' }}">
                <p class="flex items-center justify-between text-xs font-medium text-slate-500">{{ $d->name }}
                    @if ($locked)<span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">Clôturé</span>@endif
                </p>
                <p class="mt-2 text-lg font-bold text-navy-900">{{ Money::fcfa($t->total ?? 0) }}</p>
                <p class="text-xs text-slate-500">{{ $t->n ?? 0 }} vente(s)</p>
            </button>
        @endforeach
    </div>

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center">
            <label class="text-sm text-slate-600">Journée du</label>
            <input type="date" wire:model.live="date" max="{{ today()->toDateString() }}" class="hp-input sm:w-48">
            @if ($departmentFilter !== '')
                <button type="button" wire:click="$set('departmentFilter', '')" class="hp-btn-ghost text-xs">Tous les services ✕</button>
            @endif
        </div>

        @if ($sales->isEmpty())
            <x-ui.empty-state icon="concierge-bell" message="Aucune vente enregistrée pour cette journée." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50"><tr>
                        <th class="hp-th px-5">Heure</th><th class="hp-th">Service</th><th class="hp-th">Désignation</th><th class="hp-th text-right">Qté</th>
                        <th class="hp-th text-right">Montant</th><th class="hp-th">Mode</th><th class="hp-th">Saisi par</th><th class="hp-th px-5 text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sales as $s)
                            @php $canDelete = $operable->contains('id', $s->department_id) && ! in_array($s->department_id, $lockedIds); @endphp
                            <tr wire:key="sale-{{ $s->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5">{{ $s->sold_at->format('H:i') }}</td>
                                <td class="hp-td">{{ $s->department?->name }}</td>
                                <td class="hp-td font-medium text-navy-900">{{ $s->label }}</td>
                                <td class="hp-td text-right">{{ $s->quantity }}</td>
                                <td class="hp-td text-right font-semibold">{{ Money::fcfa($s->amount) }}</td>
                                <td class="hp-td">{{ $methods[$s->method] ?? $s->method }}</td>
                                <td class="hp-td text-slate-500">{{ $s->author?->name ?? '—' }}</td>
                                <td class="hp-td px-5 text-right">
                                    @if ($canDelete)
                                        <button type="button" wire:click="delete({{ $s->id }})" wire:confirm="Supprimer cette vente ?" class="hp-icon-btn hover:text-rose-600" aria-label="Supprimer la vente {{ $s->label }}"><x-ui.icon name="x" class="size-4" /></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($sales->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $sales->links() }}</div>@endif
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showForm" title="Nouvelle vente" max-width="md">
        <form wire:submit="save" id="sale-form" class="space-y-4">
            <x-ui.field label="Service" name="department_id">
                <select wire:model.live="department_id" class="hp-input">
                    @foreach ($operable as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Article du catalogue (facultatif)" name="service_id" :hint="$catalog->isEmpty() ? 'Aucun article : saisissez la désignation librement.' : null">
                <select wire:model.live="service_id" class="hp-input">
                    <option value="">— Saisie libre —</option>
                    @foreach ($catalog as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ Money::fcfa($c->price) }}</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Désignation" name="label"><input type="text" wire:model="label" class="hp-input" autocomplete="off"></x-ui.field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Quantité" name="quantity"><input type="number" min="1" wire:model.live="quantity" class="hp-input"></x-ui.field>
                <x-ui.field label="Prix unitaire (FCFA)" name="unit_price"><input type="number" min="1" wire:model.live="unit_price" class="hp-input"></x-ui.field>
            </div>
            <x-ui.field label="Mode de paiement" name="method">
                <select wire:model="method" class="hp-input">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            </x-ui.field>
            <div class="flex items-center justify-between rounded-lg bg-navy-50 px-4 py-3 text-sm">
                <span class="text-slate-600">Total</span>
                <span class="text-lg font-bold text-navy-900">{{ Money::fcfa((int) $quantity * (int) $unit_price) }}</span>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="sale-form" class="hp-btn-teal" wire:loading.attr="disabled" wire:target="save">Enregistrer la vente</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
