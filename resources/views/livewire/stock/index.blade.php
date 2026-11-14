<div class="mx-auto max-w-5xl">
    <x-ui.page-header title="Gestion des stocks" subtitle="Consommables, linge, produits d'entretien… avec alerte de stock bas.">
        <x-slot:actions>
            @if ($canManage)
                <button type="button" wire:click="openCreate" class="hp-btn-teal"><x-ui.icon name="plus" class="size-4" />Ajouter un article</button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @if ($items->isEmpty())
            <x-ui.empty-state icon="package" message="Aucun article en stock." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50"><tr>
                        <th class="hp-th px-5">Article</th><th class="hp-th text-right">Quantité</th><th class="hp-th text-right">Seuil d'alerte</th><th class="hp-th">État</th><th class="hp-th px-5 text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($items as $i)
                            <tr wire:key="it-{{ $i->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5 font-semibold text-navy-900">{{ $i->name }}</td>
                                <td class="hp-td text-right">{{ number_format($i->quantity, 0, ',', ' ') }} <span class="text-xs text-slate-400">{{ $i->unit }}</span></td>
                                <td class="hp-td text-right text-slate-500">{{ $i->alert_threshold }}</td>
                                <td class="hp-td">
                                    @if ($i->isLow())<span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">Stock bas</span>
                                    @else<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">OK</span>@endif
                                </td>
                                <td class="hp-td px-5 text-right">
                                    @if ($canManage)
                                        <button type="button" wire:click="openMove({{ $i->id }}, 'in')" class="hp-btn-ghost px-2 py-1 text-xs">+ Entrée</button>
                                        <button type="button" wire:click="openMove({{ $i->id }}, 'out')" class="hp-btn-ghost px-2 py-1 text-xs">− Sortie</button>
                                        <button type="button" wire:click="edit({{ $i->id }})" class="hp-icon-btn" aria-label="Modifier {{ $i->name }}"><x-ui.icon name="settings" class="size-4" /></button>
                                        <button type="button" wire:click="delete({{ $i->id }})" wire:confirm="Supprimer cet article ?" class="hp-icon-btn hover:text-rose-600" aria-label="Supprimer {{ $i->name }}"><x-ui.icon name="x" class="size-4" /></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Modifier l\'article' : 'Ajouter un article'" max-width="md">
        <form wire:submit="save" id="stock-form" class="space-y-4">
            <x-ui.field label="Nom de l'article" name="name"><input type="text" wire:model="name" class="hp-input" autocomplete="off"></x-ui.field>
            <div class="grid gap-3 sm:grid-cols-3">
                <x-ui.field label="Unité" name="unit"><input type="text" wire:model="unit" class="hp-input" placeholder="unité, kg, litre"></x-ui.field>
                <x-ui.field label="Quantité" name="quantity"><input type="number" min="0" wire:model="quantity" class="hp-input"></x-ui.field>
                <x-ui.field label="Seuil d'alerte" name="alert_threshold"><input type="number" min="0" wire:model="alert_threshold" class="hp-input"></x-ui.field>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="stock-form" class="hp-btn-teal">Enregistrer</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showMove" :title="$moveType === 'out' ? 'Sortie de stock' : 'Entrée de stock'" max-width="md">
        <form wire:submit="saveMove" id="move-form">
            <x-ui.field label="Quantité" name="moveQty"><input type="number" min="1" wire:model="moveQty" class="hp-input" autofocus></x-ui.field>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="move-form" class="hp-btn-teal">Valider</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
