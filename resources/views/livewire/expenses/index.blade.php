@php use App\Support\Money; @endphp
<div class="mx-auto max-w-7xl">
    <x-ui.page-header title="Dépenses" :subtitle="'Total sur la période : '.Money::fcfa($total)">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="hp-btn-teal"><x-ui.icon name="plus" class="size-4" />Enregistrer une dépense</button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row">
            <select wire:model.live="period" class="hp-input sm:w-48" aria-label="Période">
                @foreach ($periods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
            <select wire:model.live="category" class="hp-input sm:w-72" aria-label="Catégorie">
                <option value="">Toutes les catégories</option>
                @foreach ($categories as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
        </div>

        @if ($expenses->isEmpty())
            <x-ui.empty-state icon="trending-down" message="Aucune dépense sur cette période." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50"><tr>
                        <th class="hp-th px-5">Date</th><th class="hp-th">Libellé</th><th class="hp-th">Catégorie</th><th class="hp-th text-right">Montant</th><th class="hp-th px-5 text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($expenses as $e)
                            <tr wire:key="e-{{ $e->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5">{{ $e->spent_at->format('d/m/Y') }}</td>
                                <td class="hp-td font-medium text-navy-900">{{ $e->label }}</td>
                                <td class="hp-td">{{ $categories[$e->category] ?? $e->category }}</td>
                                <td class="hp-td text-right font-semibold">{{ Money::fcfa($e->amount) }}</td>
                                <td class="hp-td px-5 text-right">
                                    <button type="button" wire:click="delete({{ $e->id }})" wire:confirm="Supprimer cette dépense ?" class="hp-icon-btn hover:text-rose-600" aria-label="Supprimer {{ $e->label }}"><x-ui.icon name="x" class="size-4" /></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($expenses->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $expenses->links() }}</div>@endif
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showForm" title="Enregistrer une dépense" max-width="md">
        <form wire:submit="save" id="expense-form" class="space-y-4">
            <x-ui.field label="Libellé" name="label"><input type="text" wire:model="label" class="hp-input" placeholder="ex. Facture CIE" autocomplete="off"></x-ui.field>
            <x-ui.field label="Catégorie" name="expCategory">
                <select wire:model="expCategory" class="hp-input">@foreach ($categories as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            </x-ui.field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Montant (FCFA)" name="amount"><input type="number" min="1" step="1" wire:model="amount" class="hp-input"></x-ui.field>
                <x-ui.field label="Date" name="spent_at"><input type="date" wire:model="spent_at" max="{{ today()->toDateString() }}" class="hp-input"></x-ui.field>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="expense-form" class="hp-btn-teal">Enregistrer</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
