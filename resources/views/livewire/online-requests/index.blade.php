@php use App\Support\Money; @endphp
<div class="mx-auto max-w-5xl">
    <x-ui.page-header title="Demandes en ligne" subtitle="Les réservations faites par vos clients depuis la page publique de votre hôtel.">
        <x-slot:actions>
            @if ($counts['new'] > 0)
                <button type="button" wire:click="markAllRead" class="hp-btn-ghost"><x-ui.icon name="user-check" class="size-4" />Tout marquer comme lu</button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-3">
    <div class="inline-flex rounded-lg bg-white p-1 shadow-sm ring-1 ring-slate-200" role="tablist" aria-label="Filtrer les demandes">
        @foreach (['new' => 'Nouvelles', 'pending' => 'À traiter', 'all' => 'Toutes'] as $key => $label)
            <button type="button" role="tab" wire:click="$set('filter', '{{ $key }}')" aria-selected="{{ $filter === $key ? 'true' : 'false' }}" wire:key="tab-{{ $key }}"
                    class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $filter === $key ? 'bg-navy-900 text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">
                {{ $label }}
                <span class="rounded-full px-1.5 text-xs {{ $filter === $key ? 'bg-white/20' : 'bg-slate-100 text-slate-600' }} {{ $key === 'new' && $counts['new'] > 0 && $filter !== $key ? '!bg-rose-500 !text-white' : '' }}">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Rechercher (nom, code, téléphone…)" class="hp-input max-w-xs" aria-label="Rechercher une demande">
    </div>

    <div class="space-y-3" wire:loading.class="opacity-60">
        @forelse ($requests as $r)
            @php
                $nights = max(1, (int) $r->check_in->diffInDays($r->check_out));
                $open = in_array($r->status, ['pending', 'confirmed'], true);
            @endphp
            <article wire:key="req-{{ $r->id }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 {{ $r->read_at ? 'ring-slate-200/70' : 'ring-2 ring-teal-400' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-full bg-navy-50 font-semibold text-navy-700">{{ mb_strtoupper(mb_substr($r->guest?->name ?? '?', 0, 1)) }}</span>
                        <div>
                            <p class="font-semibold text-navy-900">{{ $r->guest?->name ?? 'Client' }}
                                @unless ($r->read_at)<span class="ml-1 rounded-full bg-teal-500 px-2 py-0.5 text-[11px] font-bold text-white">Nouveau</span>@endunless
                            </p>
                            <p class="text-xs text-slate-500">{{ $r->code }} · reçue {{ $r->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <x-ui.badge :status="$r->status" />
                </div>

                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs text-slate-500">Chambre</dt><dd class="font-medium">{{ $r->room ? 'Ch. '.$r->room->number.' · '.$r->room->type?->name : '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Séjour</dt><dd class="font-medium">{{ $r->check_in->format('d/m/Y') }} → {{ $r->check_out->format('d/m/Y') }} <span class="text-slate-400">({{ $nights }} nuit{{ $nights > 1 ? 's' : '' }})</span></dd></div>
                    <div><dt class="text-xs text-slate-500">Personnes</dt><dd class="font-medium">{{ $r->guests_count ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Montant</dt><dd class="font-semibold">{{ Money::fcfa($r->total_amount) }}</dd></div>
                </dl>

                @if ($r->guest_message)
                    <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        <p class="mb-0.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Message du client</p>
                        {{ $r->guest_message }}
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                    @if ($r->guest?->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $r->guest->phone) }}" class="hp-btn-ghost px-3 py-1.5 text-xs">Appeler {{ $r->guest->phone }}</a>@endif
                    @if ($r->guest?->email)<a href="mailto:{{ $r->guest->email }}?subject={{ rawurlencode('Votre réservation '.$r->code.' — '.$hotel->name) }}" class="hp-btn-ghost px-3 py-1.5 text-xs">Répondre par e-mail</a>@endif
                    <a href="{{ route('reservations.index', ['voir' => $r->id]) }}" class="hp-btn-ghost px-3 py-1.5 text-xs">Ouvrir la réservation</a>

                    <span class="ml-auto flex flex-wrap items-center gap-2">
                        @unless ($r->read_at)
                            <button type="button" wire:click="markRead({{ $r->id }})" wire:loading.attr="disabled" wire:target="markRead({{ $r->id }})" class="hp-btn-ghost px-3 py-1.5 text-xs">
                                <span wire:loading.remove wire:target="markRead({{ $r->id }})">Marquer comme lu</span><span wire:loading wire:target="markRead({{ $r->id }})">En cours…</span>
                            </button>
                        @endunless
                        @if ($r->read_at)
                            <button type="button" wire:click="markUnread({{ $r->id }})" wire:loading.attr="disabled" wire:target="markUnread({{ $r->id }})" class="hp-btn-ghost px-3 py-1.5 text-xs">Marquer comme non lu</button>
                        @endif
                        @if ($canManage && $open)
                            <button type="button" wire:click="openAnswer({{ $r->id }}, 'cancel')"
                                    wire:loading.attr="disabled" wire:target="decline({{ $r->id }})" class="hp-btn-ghost px-3 py-1.5 text-xs text-rose-600">
                                <span wire:loading.remove wire:target="decline({{ $r->id }})">Refuser</span><span wire:loading wire:target="decline({{ $r->id }})">En cours…</span>
                            </button>
                        @endif
                        @if ($canManage && $r->status === 'pending')
                            <button type="button" wire:click="openAnswer({{ $r->id }}, 'confirm')" wire:loading.attr="disabled" wire:target="confirm({{ $r->id }})" class="hp-btn-teal px-3 py-1.5 text-xs">
                                <span wire:loading.remove wire:target="confirm({{ $r->id }})">Confirmer</span><span wire:loading wire:target="confirm({{ $r->id }})">En cours…</span>
                            </button>
                        @endif
                        @if ($canManage && $r->status === 'confirmed')
                            <button type="button" wire:click="checkIn({{ $r->id }})" wire:loading.attr="disabled" wire:target="checkIn({{ $r->id }})" class="hp-btn-teal px-3 py-1.5 text-xs">Enregistrer l'arrivée</button>
                        @endif
                        @if ($canManage && $r->status === 'checked_in')
                            <button type="button" wire:click="checkOut({{ $r->id }})" wire:confirm="Enregistrer le départ du client ?" wire:loading.attr="disabled" wire:target="checkOut({{ $r->id }})" class="hp-btn-teal px-3 py-1.5 text-xs">Enregistrer le départ</button>
                        @endif
                    </span>
                </div>
            </article>
        @empty
            <x-ui.card>
                <x-ui.empty-state icon="mail" :message="$search !== '' ? 'Aucune demande ne correspond à votre recherche.' : ($filter === 'new' ? 'Aucune nouvelle demande. Les réservations de vos clients arriveront ici.' : 'Aucune demande dans cette liste.')" />
            </x-ui.card>
        @endforelse
    </div>

    <x-ui.modal wire:model="showAnswer" :title="$answerAction === 'confirm' ? 'Confirmer la réservation' : 'Refuser la réservation'">
        <form wire:submit="submitAnswer" class="space-y-4">
            <p class="text-sm text-slate-600">
                {{ $answerAction === 'confirm' ? 'Le client sera prévenu par e-mail que son séjour est confirmé.' : 'Le client sera prévenu par e-mail que sa demande est refusée et les dates seront libérées.' }}
            </p>
            <x-ui.field label="Message pour le client (facultatif)" name="answerMessage" hint="Visible dans l'e-mail et dans « Suivre ma réservation ».">
                <textarea wire:model="answerMessage" rows="4" maxlength="1000" class="hp-input" placeholder="{{ $answerAction === 'confirm' ? 'Ex. Bienvenue ! Arrivée possible dès 14h.' : 'Ex. Désolé, nous sommes complets à ces dates.' }}"></textarea>
            </x-ui.field>
            <div class="flex justify-end gap-2">
                <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="submitAnswer" class="{{ $answerAction === 'confirm' ? 'hp-btn-teal' : 'hp-btn-ghost text-rose-600' }}">
                    {{ $answerAction === 'confirm' ? 'Confirmer et prévenir le client' : 'Refuser et prévenir le client' }}
                </button>
            </div>
        </form>
    </x-ui.modal>

    @if ($requests->hasPages())<div class="mt-4">{{ $requests->links() }}</div>@endif
</div>
