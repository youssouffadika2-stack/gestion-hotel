@php use App\Support\Money; @endphp
<div class="mx-auto max-w-7xl">
    <x-ui.page-header title="Facturation" subtitle="Factures de séjour et soldes à encaisser." />

    <x-ui.card :padding="false">
        <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Code ou nom du client…" class="hp-input pl-9">
            </div>
            <select wire:model.live="state" class="hp-input sm:w-52" aria-label="État de paiement">
                <option value="">Toutes les factures</option>
                <option value="unpaid">Impayées</option>
                <option value="partial">Partiellement payées</option>
                <option value="paid">Payées</option>
            </select>
        </div>

        @if ($invoices->isEmpty())
            <x-ui.empty-state icon="receipt" message="Aucune facture ne correspond à votre recherche." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50"><tr>
                        <th class="hp-th px-5">Facture</th><th class="hp-th">Client</th><th class="hp-th">Séjour</th>
                        <th class="hp-th text-right">Total</th><th class="hp-th text-right">Payé</th><th class="hp-th text-right">Solde</th><th class="hp-th">État</th><th class="hp-th px-5 text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoices as $i)
                            @php $paid = (int) $i->paid; $balance = max(0, $i->total_amount - $paid); @endphp
                            <tr wire:key="i-{{ $i->id }}" class="hover:bg-slate-50/60">
                                <td class="hp-td px-5 font-semibold text-navy-900">F-{{ $i->code }}</td>
                                <td class="hp-td">{{ $i->guest?->name }}</td>
                                <td class="hp-td">{{ $i->check_in->format('d/m') }} → {{ $i->check_out->format('d/m/Y') }}</td>
                                <td class="hp-td text-right">{{ Money::fcfa($i->total_amount) }}</td>
                                <td class="hp-td text-right text-emerald-600">{{ Money::fcfa($paid) }}</td>
                                <td class="hp-td text-right {{ $balance ? 'font-semibold text-rose-600' : 'text-slate-400' }}">{{ Money::fcfa($balance) }}</td>
                                <td class="hp-td">
                                    @if ($balance === 0)<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Payée</span>
                                    @elseif ($paid > 0)<span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Partielle</span>
                                    @else<span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">Impayée</span>@endif
                                </td>
                                <td class="hp-td px-5 text-right">
                                    <a href="{{ route('invoices.show', $i) }}" target="_blank" class="hp-icon-btn" title="Voir / imprimer" aria-label="Voir la facture {{ $i->code }}"><x-ui.icon name="eye" class="size-4" /></a>
                                    @if ($balance > 0)
                                        <a href="{{ route('payments.index', ['reservation' => $i->id, 'nouveau' => 1]) }}" class="hp-icon-btn" title="Encaisser" aria-label="Encaisser {{ $i->code }}"><x-ui.icon name="wallet" class="size-4" /></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $invoices->links() }}</div>@endif
        @endif
    </x-ui.card>
</div>
