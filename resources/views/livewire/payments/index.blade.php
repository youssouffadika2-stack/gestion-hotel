@php use App\Support\Money; @endphp
<div class="mx-auto max-w-7xl">
    <x-ui.page-header title="Paiements" :subtitle="'Encaissé aujourd\'hui : '.Money::fcfa($total)">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="hp-btn-teal"><x-ui.icon name="plus" class="size-4" />Enregistrer un paiement</button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <div class="border-b border-slate-100 p-4">
            <select wire:model.live="method" class="hp-input sm:w-60" aria-label="Filtrer par mode de paiement">
                <option value="">Tous les modes de paiement</option>
                @foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
        </div>

        @if ($payments->isEmpty())
            <x-ui.empty-state icon="wallet" message="Aucun paiement enregistré." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50"><tr>
                        <th class="hp-th px-5">Date</th><th class="hp-th">Réservation</th><th class="hp-th">Client</th><th class="hp-th">Mode</th><th class="hp-th px-5 text-right">Montant</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payments as $p)
                            <tr wire:key="p-{{ $p->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5">{{ $p->paid_at->format('d/m/Y H:i') }}</td>
                                <td class="hp-td font-semibold text-navy-900">{{ $p->reservation?->code ?? '—' }}</td>
                                <td class="hp-td">{{ $p->reservation?->guest?->name ?? '—' }}</td>
                                <td class="hp-td">{{ $methods[$p->method] ?? $p->method }}</td>
                                <td class="hp-td px-5 text-right font-semibold">{{ Money::fcfa($p->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $payments->links() }}</div>@endif
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showForm" title="Enregistrer un paiement" max-width="md">
        <form wire:submit="save" id="payment-form" class="space-y-4">
            <x-ui.field label="Réservation" name="reservation_id" :hint="$payable->isEmpty() ? 'Aucune réservation avec un solde à payer.' : null">
                <select wire:model.live="reservation_id" class="hp-input">
                    <option value="">— Choisir —</option>
                    @foreach ($payable as $r)
                        <option value="{{ $r->id }}">{{ $r->code }} · {{ $r->guest?->name }} · reste {{ Money::fcfa($r->balance) }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Montant (FCFA)" name="amount"><input type="number" min="1" step="1" wire:model="amount" class="hp-input"></x-ui.field>
            <x-ui.field label="Mode de paiement" name="payMethod">
                <select wire:model="payMethod" class="hp-input">
                    @foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </x-ui.field>
        </form>
        <x-slot:footer>
            <button type="button" @click="open = false" class="hp-btn-ghost">Annuler</button>
            <button type="submit" form="payment-form" class="hp-btn-teal" wire:loading.attr="disabled" wire:target="save">Enregistrer</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
