@php use App\Support\Money; @endphp
<div class="mx-auto max-w-5xl space-y-5">
    <x-ui.page-header title="Services" subtitle="Les services de l'hôtel (Réception, Restaurant, Bar…) et leur catalogue de plats, boissons et prestations.">
        <x-slot:actions>
            <button type="button" wire:click="openDept" class="hp-btn-teal"><x-ui.icon name="plus" class="size-4" />Nouveau service</button>
            <button type="button" wire:click="openCreate" class="hp-btn-ghost"><x-ui.icon name="plus" class="size-4" />Ajouter un article</button>
        </x-slot:actions>
    </x-ui.page-header>

    @foreach ($departments as $d)
        <x-ui.card :padding="false" wire:key="dept-{{ $d->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3.5">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700"><x-ui.icon :name="match($d->type) { 'reception' => 'building-2', 'restaurant' => 'concierge-bell', 'bar' => 'banknote', 'laundry' => 'package', default => 'circle-dot' }" class="size-4" /></span>
                    <div>
                        <h2 class="text-sm font-bold text-navy-900">{{ $d->name }}
                            @unless ($d->active)<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">Inactif</span>@endunless
                        </h2>
                        <p class="text-xs text-slate-500">{{ $types[$d->type] ?? $d->type }} · {{ $d->users_count }} employé(s) affecté(s)</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="openCreate({{ $d->id }})" class="hp-btn-ghost px-2.5 py-1 text-xs">+ Article</button>
                    <button type="button" wire:click="editDept({{ $d->id }})" class="hp-icon-btn" aria-label="Modifier {{ $d->name }}"><x-ui.icon name="settings" class="size-4" /></button>
                    @unless ($d->isReception())
                        <button type="button" wire:click="toggleDept({{ $d->id }})" class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $d->active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-300' }}">{{ $d->active ? 'Actif' : 'Activer' }}</button>
                        <button type="button" wire:click="deleteDept({{ $d->id }})" wire:confirm="Supprimer le service {{ $d->name }} ?" class="hp-icon-btn hover:text-rose-600" aria-label="Supprimer {{ $d->name }}"><x-ui.icon name="x" class="size-4" /></button>
                    @endunless
                </div>
            </div>

            @forelse ($d->services as $s)
                <div wire:key="svc-{{ $s->id }}" class="flex items-center justify-between gap-3 border-b border-slate-50 px-5 py-2.5 last:border-0">
                    <div class="min-w-0"><p class="truncate text-sm text-navy-900">{{ $s->name }}</p><p class="text-xs text-slate-500">{{ Money::fcfa($s->price) }}</p></div>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="toggle({{ $s->id }})" class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $s->active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-300' }}">{{ $s->active ? 'Actif' : 'Inactif' }}</button>
                        <button type="button" wire:click="edit({{ $s->id }})" class="hp-icon-btn" aria-label="Modifier {{ $s->name }}"><x-ui.icon name="settings" class="size-4" /></button>
                        <button type="button" wire:click="delete({{ $s->id }})" wire:confirm="Supprimer cet article ?" class="hp-icon-btn hover:text-rose-600" aria-label="Supprimer {{ $s->name }}"><x-ui.icon name="x" class="size-4" /></button>
                    </div>
                </div>
            @empty
                <p class="px-5 py-4 text-sm text-slate-400">Aucun article dans ce service.@if ($d->isReception()) (Les séjours sont facturés depuis les réservations.)@endif</p>
            @endforelse
        </x-ui.card>
    @endforeach

    @if ($orphans->isNotEmpty())
        <x-ui.card title="Articles sans service">
            @foreach ($orphans as $s)
                <div wire:key="orph-{{ $s->id }}" class="flex items-center justify-between py-1.5 text-sm">
                    <span>{{ $s->name }} · {{ Money::fcfa($s->price) }}</span>
                    <button type="button" wire:click="edit({{ $s->id }})" class="hp-btn-ghost px-2.5 py-1 text-xs">Rattacher à un service</button>
                </div>
            @endforeach
        </x-ui.card>
    @endif

    <x-ui.modal wire:model="showDept" :title="$deptId ? 'Modifier le service' : 'Nouveau service'" max-width="md">
        <form wire:submit="saveDept" id="dept-form" class="space-y-4">
            <x-ui.field label="Nom du service" name="dept_name"><input type="text" wire:model="dept_name" class="hp-input" placeholder="ex. Restaurant, Bar, Spa" autocomplete="off"></x-ui.field>
            <x-ui.field label="Type" name="dept_type">
                <select wire:model="dept_type" class="hp-input" @disabled($deptId && $dept_type === 'reception')>
                    @foreach ($types as $k => $l)
                        @if ($k !== 'reception' || ($deptId && $dept_type === 'reception'))<option value="{{ $k }}">{{ $l }}</option>@endif
                    @endforeach
                </select>
            </x-ui.field>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="dept-form" class="hp-btn-teal">Enregistrer</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Modifier l\'article' : 'Ajouter un article'" max-width="md">
        <form wire:submit="save" id="service-form" class="space-y-4">
            <x-ui.field label="Service" name="department_id">
                <select wire:model="department_id" class="hp-input">
                    <option value="">— Choisir —</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Nom (plat, boisson, prestation)" name="name"><input type="text" wire:model="name" class="hp-input" autocomplete="off"></x-ui.field>
            <x-ui.field label="Prix (FCFA)" name="price"><input type="number" min="0" step="100" wire:model="price" class="hp-input"></x-ui.field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="active" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"> Article actif</label>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="service-form" class="hp-btn-teal">Enregistrer</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
