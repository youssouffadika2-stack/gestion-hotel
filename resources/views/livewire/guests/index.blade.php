@php use App\Support\Money; @endphp
<div class="mx-auto max-w-7xl">
    <x-ui.page-header title="Clients" subtitle="Votre fichier clients.">
        <x-slot:actions>
            @if ($canManage)
                <button type="button" wire:click="openCreate" class="hp-btn-teal"><x-ui.icon name="user-plus" class="size-4" />Ajouter un client</button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="border-b border-slate-100 p-4">
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Nom, téléphone ou e-mail…" class="hp-input pl-9">
            </div>
        </div>

        @if ($guests->isEmpty())
            <x-ui.empty-state icon="users" message="Aucun client trouvé." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50"><tr>
                        <th class="hp-th px-5">Nom</th><th class="hp-th">Téléphone</th><th class="hp-th">E-mail</th><th class="hp-th text-right">Séjours</th><th class="hp-th px-5 text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($guests as $g)
                            <tr wire:key="g-{{ $g->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5 font-semibold text-navy-900">{{ $g->name }}</td>
                                <td class="hp-td">{{ $g->phone ?: '—' }}</td>
                                <td class="hp-td">{{ $g->email ?: '—' }}</td>
                                <td class="hp-td text-right">{{ $g->reservations_count }}</td>
                                <td class="hp-td px-5 text-right">
                                    @if ($canManage)
                                        <button type="button" wire:click="edit({{ $g->id }})" class="hp-icon-btn" aria-label="Modifier {{ $g->name }}"><x-ui.icon name="settings" class="size-4" /></button>
                                        <button type="button" wire:click="delete({{ $g->id }})" wire:confirm="Supprimer ce client ?" class="hp-icon-btn hover:text-rose-600" aria-label="Supprimer {{ $g->name }}"><x-ui.icon name="x" class="size-4" /></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($guests->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $guests->links() }}</div>@endif
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Modifier le client' : 'Ajouter un client'" max-width="md">
        <form wire:submit="save" id="guest-form" class="space-y-4">
            <x-ui.field label="Nom complet" name="name"><input type="text" wire:model="name" class="hp-input" autocomplete="off"></x-ui.field>
            <x-ui.field label="Téléphone" name="phone"><input type="tel" wire:model="phone" class="hp-input" autocomplete="off"></x-ui.field>
            <x-ui.field label="E-mail" name="email"><input type="email" wire:model="email" class="hp-input" autocomplete="off"></x-ui.field>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="guest-form" class="hp-btn-teal">Enregistrer</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
