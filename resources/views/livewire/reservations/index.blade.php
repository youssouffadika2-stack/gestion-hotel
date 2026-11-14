@php use App\Support\Money; @endphp
<div class="mx-auto max-w-7xl">
    <x-ui.page-header title="Réservations" subtitle="Créez, suivez et gérez les séjours de vos clients.">
        <x-slot:actions>
            @if ($canManage)
                <button type="button" wire:click="openCreate" class="hp-btn-teal"><x-ui.icon name="plus" class="size-4" />Nouvelle réservation</button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Code, nom ou téléphone du client…" class="hp-input pl-9">
            </div>
            <select wire:model.live="status" class="hp-input sm:w-52" aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                @foreach ($labels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($reservations->isEmpty())
            <x-ui.empty-state icon="calendar-check" message="Aucune réservation ne correspond à votre recherche." />
        @else
            <div class="overflow-x-auto" wire:loading.class="opacity-60" wire:target="search,status,gotoPage,nextPage,previousPage">
                <table class="min-w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="hp-th px-5">Code</th><th class="hp-th">Client</th><th class="hp-th">Chambre</th>
                            <th class="hp-th">Arrivée</th><th class="hp-th">Départ</th>
                            <th class="hp-th text-right">Montant</th><th class="hp-th text-right">Reste</th>
                            <th class="hp-th">Statut</th><th class="hp-th px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($reservations as $r)
                            <tr wire:key="r-{{ $r->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5 font-semibold text-navy-900">{{ $r->code }}@if ($r->source === 'online') <span class="ml-1 rounded-full bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold text-teal-700 ring-1 ring-inset ring-teal-600/20">En ligne</span>@endif</td>
                                <td class="hp-td">{{ $r->guest?->name ?? '—' }}</td>
                                <td class="hp-td">{{ $r->room?->number ?? '—' }}</td>
                                <td class="hp-td">{{ $r->check_in->format('d/m/Y') }}</td>
                                <td class="hp-td">{{ $r->check_out->format('d/m/Y') }}</td>
                                <td class="hp-td text-right font-medium">{{ Money::fcfa($r->total_amount) }}</td>
                                <td class="hp-td text-right {{ $r->total_amount - (int) $r->paid > 0 && $r->status !== 'cancelled' ? 'text-rose-600' : 'text-slate-400' }}">{{ Money::fcfa(max(0, $r->total_amount - (int) $r->paid)) }}</td>
                                <td class="hp-td"><x-ui.badge :status="$r->status" /></td>
                                <td class="hp-td px-5 text-right">
                                    <button type="button" wire:click="show({{ $r->id }})" class="hp-icon-btn" title="Voir le détail" aria-label="Voir {{ $r->code }}"><x-ui.icon name="eye" class="size-4" /></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($reservations->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">{{ $reservations->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    {{-- ============ Formulaire création / modification ============ --}}
    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Modifier la réservation' : 'Nouvelle réservation'" max-width="xl">
        <form wire:submit="save" id="reservation-form" class="space-y-4">
            <div class="rounded-lg bg-slate-50 p-3">
                <div class="mb-2 flex gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="guestMode" value="existing" class="text-teal-600 focus:ring-teal-500"> Client existant</label>
                    <label class="flex items-center gap-2"><input type="radio" wire:model.live="guestMode" value="new" class="text-teal-600 focus:ring-teal-500"> Nouveau client</label>
                </div>
                @if ($guestMode === 'existing')
                    <x-ui.field label="Client" name="guest_id">
                        <select wire:model="guest_id" class="hp-input">
                            <option value="">— Choisir un client —</option>
                            @foreach ($guests as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}@if ($g->phone) ({{ $g->phone }})@endif</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field label="Nom complet" name="guest_name"><input type="text" wire:model="guest_name" class="hp-input" autocomplete="off"></x-ui.field>
                        <x-ui.field label="Téléphone" name="guest_phone"><input type="tel" wire:model="guest_phone" class="hp-input" autocomplete="off"></x-ui.field>
                    </div>
                @endif
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Date d'arrivée" name="check_in"><input type="date" wire:model.live="check_in" @if(! $editingId) min="{{ today()->toDateString() }}" @endif class="hp-input"></x-ui.field>
                <x-ui.field label="Date de départ" name="check_out"><input type="date" wire:model.live="check_out" min="{{ $check_in }}" class="hp-input"></x-ui.field>
            </div>

            <x-ui.field label="Chambre disponible" name="room_id"
                        :hint="$this->nights < 1 ? 'Choisissez d\'abord des dates valides.' : $this->availableRooms->count().' chambre(s) libre(s) pour '.$this->nights.' nuit(s)'">
                <select wire:model.live="room_id" class="hp-input" @disabled($this->nights < 1)>
                    <option value="">— Choisir une chambre —</option>
                    @foreach ($this->availableRooms as $room)
                        <option value="{{ $room->id }}">Ch. {{ $room->number }} — {{ $room->type?->name }} ({{ Money::fcfa($room->type?->base_price) }}/nuit)</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Statut" name="formStatus">
                <select wire:model="formStatus" class="hp-input">
                    <option value="pending">En attente</option>
                    <option value="confirmed">Confirmée</option>
                </select>
            </x-ui.field>

            <div class="flex items-center justify-between rounded-lg bg-navy-50 px-4 py-3 text-sm">
                <span class="text-slate-600">Montant total ({{ $this->nights }} nuit(s))</span>
                <span class="text-lg font-bold text-navy-900">{{ Money::fcfa($this->estimatedTotal) }}</span>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="reservation-form" class="hp-btn-teal" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Enregistrer</span><span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- ============ Détail ============ --}}
    <x-ui.modal wire:model="showDetail" :title="$detail ? 'Réservation '.$detail->code : 'Réservation'" max-width="xl">
        @if ($detail)
            @php $balance = max(0, $detail->total_amount - (int) $detail->paid); @endphp
            <div class="flex items-center justify-between">
                <x-ui.badge :status="$detail->status" class="text-sm" />
                <span class="text-xs text-slate-400">Créée le {{ $detail->created_at->format('d/m/Y à H:i') }}</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div><dt class="text-xs text-slate-500">Client</dt><dd class="font-semibold text-navy-900">{{ $detail->guest?->name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Téléphone</dt><dd>{{ $detail->guest?->phone ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Chambre</dt><dd>{{ $detail->room ? $detail->room->number.' · '.$detail->room->type?->name : '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Séjour</dt><dd>{{ $detail->check_in->format('d/m/Y') }} → {{ $detail->check_out->format('d/m/Y') }}</dd></div>
                <div><dt class="text-xs text-slate-500">Montant total</dt><dd class="font-semibold">{{ Money::fcfa($detail->total_amount) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Payé / Reste</dt><dd><span class="text-emerald-600">{{ Money::fcfa($detail->paid) }}</span> / <span class="{{ $balance ? 'text-rose-600' : 'text-slate-400' }}">{{ Money::fcfa($balance) }}</span></dd></div>
            </dl>

            @if ($detail->guest_message)
                <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm"><p class="mb-0.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Message du client</p>{{ $detail->guest_message }}</div>
            @endif

            <h4 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Paiements</h4>
            @forelse ($detail->payments as $p)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm">
                    <span>{{ $p->paid_at->format('d/m/Y H:i') }} · {{ ['cash' => 'Espèces', 'mobile_money' => 'Mobile Money', 'card' => 'Carte', 'transfer' => 'Virement'][$p->method] ?? $p->method }}</span>
                    <span class="font-semibold">{{ Money::fcfa($p->amount) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Aucun paiement enregistré.</p>
            @endforelse
        @endif

        <x-slot:footer>
            @if ($detail)
                @can('viewFinance', app(\App\Services\CurrentHotel::class)->get())
                    @if ($detail->status !== 'cancelled')
                        <a href="{{ route('invoices.show', $detail) }}" target="_blank" class="hp-btn-ghost"><x-ui.icon name="receipt" class="size-4" />Facture</a>
                    @endif
                @endcan
                @if ($canPay && $detail->status !== 'cancelled' && $detail->total_amount > (int) $detail->paid)
                    <a href="{{ route('payments.index', ['reservation' => $detail->id, 'nouveau' => 1]) }}" class="hp-btn-ghost"><x-ui.icon name="wallet" class="size-4" />Encaisser</a>
                @endif
                @if ($canManage)
                    @if (in_array($detail->status, ['pending', 'confirmed']))
                        <button type="button" wire:click="edit({{ $detail->id }})" class="hp-btn-ghost">Modifier</button>
                        <button type="button" wire:click="cancel({{ $detail->id }})" wire:confirm="Annuler cette réservation ?" class="hp-btn-ghost text-rose-600">Annuler</button>
                    @endif
                    @if ($detail->status === 'pending')
                        <button type="button" wire:click="confirm({{ $detail->id }})" class="hp-btn-primary">Confirmer</button>
                    @endif
                    @if (in_array($detail->status, ['pending', 'confirmed']))
                        <button type="button" wire:click="checkIn({{ $detail->id }})" class="hp-btn-teal">Enregistrer l'arrivée</button>
                    @endif
                    @if ($detail->status === 'checked_in')
                        <button type="button" wire:click="checkOut({{ $detail->id }})" wire:confirm="Enregistrer le départ du client ?" class="hp-btn-teal">Enregistrer le départ</button>
                    @endif
                @endif
            @endif
        </x-slot:footer>
    </x-ui.modal>
</div>
