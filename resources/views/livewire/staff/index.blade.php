<div class="mx-auto max-w-5xl space-y-5">
    <x-ui.page-header title="Personnel et accès" subtitle="Vous êtes l'administrateur : donnez à chaque employé le rôle et les services dont il a besoin.">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="hp-btn-teal"><x-ui.icon name="user-plus" class="size-4" />Ajouter un employé</button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <ul class="divide-y divide-slate-100">
            @foreach ($members as $m)
                <li wire:key="m-{{ $m->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-navy-50 text-sm font-semibold text-navy-700">{{ mb_strtoupper(mb_substr($m->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-navy-900">{{ $m->name }}@if ($m->id === auth()->id()) <span class="text-xs font-normal text-slate-400">(vous)</span>@endif</p>
                            <p class="truncate text-xs text-slate-500">{{ $m->email }}</p>
                            @if ($m->departments->isNotEmpty())
                                <p class="mt-1 flex flex-wrap gap-1">@foreach ($m->departments as $d)<span class="rounded-full bg-teal-50 px-2 py-0.5 text-[11px] font-medium text-teal-700 ring-1 ring-inset ring-teal-600/20">{{ $d->name }}</span>@endforeach</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $roles[$m->pivot->role] ?? $m->pivot->role }}</span>
                        @if ($m->id !== auth()->id())
                            <button type="button" wire:click="edit({{ $m->id }})" class="hp-icon-btn" title="Modifier les accès" aria-label="Modifier les accès de {{ $m->name }}"><x-ui.icon name="settings" class="size-4" /></button>
                            <button type="button" wire:click="remove({{ $m->id }})" wire:confirm="Retirer l'accès de {{ $m->name }} ?" class="hp-icon-btn hover:text-rose-600" aria-label="Retirer {{ $m->name }}"><x-ui.icon name="x" class="size-4" /></button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    <x-ui.card title="Que peut faire chaque rôle ?">
        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            @foreach ($roles as $k => $label)
                <div><dt class="font-semibold text-navy-900">{{ $label }}</dt><dd class="text-slate-500">{{ $roleHelp[$k] }}</dd></div>
            @endforeach
        </dl>
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingUserId ? 'Modifier les accès' : 'Ajouter un employé'" max-width="lg">
        <form wire:submit="save" id="staff-form" class="space-y-4">
            @if (! $editingUserId)
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field label="Nom complet" name="name"><input type="text" wire:model="name" class="hp-input" autocomplete="off"></x-ui.field>
                    <x-ui.field label="E-mail (identifiant de connexion)" name="email"><input type="email" wire:model="email" class="hp-input" autocomplete="off"></x-ui.field>
                </div>
                <x-ui.field label="Mot de passe provisoire" name="password" hint="8 caractères minimum. L'employé pourra le modifier depuis son profil."><input type="password" wire:model="password" class="hp-input" autocomplete="new-password"></x-ui.field>
            @else
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm"><strong>{{ $name }}</strong> · {{ $email }}</p>
            @endif

            <x-ui.field label="Rôle" name="role">
                <select wire:model.live="role" class="hp-input">@foreach ($roles as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <p class="mt-1 text-xs text-slate-500">{{ $roleHelp[$role] ?? '' }}</p>
            </x-ui.field>

            @if (in_array($role, ['staff', 'receptionist', 'accountant']))
                <fieldset>
                    <legend class="mb-1 text-xs font-semibold text-slate-600">Services affectés <span class="font-normal text-slate-400">(où l'employé peut vendre et faire son point)</span></legend>
                    @if ($role === 'receptionist')<p class="mb-1 text-xs text-slate-400">La Réception est automatiquement accessible au réceptionniste.</p>@endif
                    @if ($role === 'accountant')<p class="mb-1 text-xs text-slate-400">Le comptable consulte les services mais n'y fait pas de vente.</p>@endif
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        @foreach ($departments as $d)
                            <label class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-200 hover:bg-slate-50">
                                <input type="checkbox" wire:model="departmentIds" value="{{ $d->id }}" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"> {{ $d->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="staff-form" class="hp-btn-teal">{{ $editingUserId ? 'Enregistrer' : 'Ajouter' }}</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
