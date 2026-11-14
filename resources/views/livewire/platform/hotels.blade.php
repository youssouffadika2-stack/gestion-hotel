@php
    $badge = [
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'suspended' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    ];
@endphp
<div class="space-y-5">
    <x-ui.page-header title="Hôtels inscrits" subtitle="Activez les nouveaux hôtels et donnez à leur administrateur ses accès de connexion." />

    <div class="grid grid-cols-3 gap-3">
        @foreach ($labels as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $status === $key ? '' : $key }}')" wire:key="cnt-{{ $key }}"
                    class="rounded-xl bg-white p-4 text-left shadow-sm ring-1 transition hover:shadow-md {{ $status === $key ? 'ring-2 ring-teal-500' : 'ring-slate-200/70' }}">
                <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-navy-900">{{ $counts[$key] ?? 0 }}</p>
                @if ($key === 'pending' && ($counts['pending'] ?? 0) > 0)<p class="text-xs font-semibold text-amber-600">À traiter</p>@endif
            </button>
        @endforeach
    </div>

    <x-ui.card :padding="false">
        <div class="border-b border-slate-100 p-4">
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Nom de l'hôtel, ville, nom ou e-mail de l'administrateur…" class="hp-input pl-9">
            </div>
        </div>

        @if ($hotels->isEmpty())
            <x-ui.empty-state icon="building-2" message="Aucun hôtel ne correspond." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50"><tr>
                        <th class="hp-th px-5">Hôtel</th><th class="hp-th">Administrateur</th><th class="hp-th text-right">Comptes</th>
                        <th class="hp-th">Inscrit le</th><th class="hp-th">Statut</th><th class="hp-th px-5 text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($hotels as $h)
                            @php $owner = $h->users->first(); @endphp
                            <tr wire:key="h-{{ $h->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5"><span class="font-semibold text-navy-900">{{ $h->name }}</span><span class="block text-xs text-slate-500">{{ $h->city ?: '—' }}</span></td>
                                <td class="hp-td">@if ($owner){{ $owner->name }}<span class="block text-xs text-slate-500">{{ $owner->email }}</span>@else<span class="text-slate-400">—</span>@endif</td>
                                <td class="hp-td text-right">{{ $h->users_count }}</td>
                                <td class="hp-td">{{ $h->created_at?->format('d/m/Y') }}</td>
                                <td class="hp-td"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $badge[$h->status] ?? '' }}">{{ $labels[$h->status] ?? $h->status }}</span></td>
                                <td class="hp-td px-5 text-right">
                                    @if ($h->status !== 'active')
                                        <button type="button" wire:click="activate({{ $h->id }})" wire:loading.attr="disabled" class="hp-btn-teal px-2.5 py-1 text-xs">{{ $h->status === 'pending' ? 'Activer' : 'Réactiver' }}</button>
                                    @else
                                        <button type="button" wire:click="suspend({{ $h->id }})" wire:confirm="Suspendre « {{ $h->name }} » ? Ses utilisateurs ne pourront plus se connecter." class="hp-btn-ghost px-2.5 py-1 text-xs text-rose-600">Suspendre</button>
                                    @endif
                                    <button type="button" wire:click="openAccess({{ $h->id }})" class="hp-btn-ghost px-2.5 py-1 text-xs">Accès</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($hotels->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $hotels->links() }}</div>@endif
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showAccess" :title="'Accès de '.($accessHotel->name ?? 'l\'hôtel')" max-width="md">
        @if ($revealedPassword)
            <div class="space-y-3" x-data="{ copied: false }">
                <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">Mot de passe enregistré. Communiquez ces accès à l'administrateur de l'hôtel.</p>
                <dl class="space-y-2 rounded-lg bg-slate-50 p-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Adresse</dt><dd class="font-medium">{{ url('/login') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">E-mail</dt><dd class="font-medium">{{ $revealedEmail }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Mot de passe</dt><dd class="font-mono font-bold text-navy-900">{{ $revealedPassword }}</dd></div>
                </dl>
                <button type="button" class="hp-btn-ghost w-full"
                        @click="navigator.clipboard.writeText(@js('Connexion : '.url('/login')."\nE-mail : ".$revealedEmail."\nMot de passe : ".$revealedPassword)); copied = true">
                    <span x-show="! copied">Copier les accès</span><span x-show="copied" x-cloak>Copié ✓</span>
                </button>
                @if ($emailSent === true)<p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">✉ Les accès ont aussi été envoyés par e-mail à {{ $revealedEmail }}.</p>
                @elseif ($emailSent === false)<p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">L'e-mail n'a pas pu être envoyé : communiquez les accès vous-même.</p>@endif
                <p class="text-xs text-slate-400">Ce mot de passe n'est affiché qu'une fois. L'administrateur pourra le changer depuis « Mon profil ».</p>
            </div>
        @else
            <form wire:submit="saveAccess" id="access-form" class="space-y-4">
                @if ($owners->isEmpty())
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Cet hôtel n'a aucun administrateur.</p>
                @else
                    <x-ui.field label="Administrateur de l'hôtel" name="ownerId">
                        <select wire:model="ownerId" class="hp-input">
                            @foreach ($owners as $o)<option value="{{ $o->id }}">{{ $o->name }} — {{ $o->email }}</option>@endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Nouveau mot de passe" name="newPassword" hint="8 caractères minimum. Il remplace l'ancien mot de passe de cet administrateur.">
                        <div class="flex gap-2">
                            <input type="text" wire:model="newPassword" class="hp-input font-mono" autocomplete="off" spellcheck="false" placeholder="Saisissez ou générez">
                            <button type="button" wire:click="generatePassword" class="hp-btn-ghost shrink-0">Générer</button>
                        </div>
                    </x-ui.field>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="sendEmail" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"> Envoyer ces accès par e-mail à l'hôtel</label>
                @endif
            </form>
        @endif

        <x-slot:footer>
            @if ($revealedPassword)
                <button type="button" wire:click="closeAccess" class="hp-btn-primary">Terminé</button>
            @else
                <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
                @if ($owners->isNotEmpty())<button type="submit" form="access-form" class="hp-btn-teal" wire:loading.attr="disabled" wire:target="saveAccess">Enregistrer le mot de passe</button>@endif
            @endif
        </x-slot:footer>
    </x-ui.modal>
</div>
