<?php

namespace App\Livewire\Invoices;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Facturation')]
class Index extends Component
{
    use InteractsWithHotel;
    use WithPagination;

    #[Url(as: 'recherche', except: '')]
    public string $search = '';

    #[Url(as: 'etat', except: '')]
    public string $state = ''; // unpaid | partial | paid

    public function mount(): void
    {
        $this->authorizeHotel('viewFinance');
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingState(): void { $this->resetPage(); }

    public function render()
    {
        $hotel = $this->authorizeHotel('viewFinance');

        $query = Reservation::where('hotel_id', $hotel->id)->where('status', '!=', Reservation::CANCELLED)
            ->with('guest:id,name')->withSum('payments as paid', 'amount');

        if ($term = trim($this->search)) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('code', 'like', $like)->orWhereHas('guest', fn ($g) => $g->where('name', 'like', $like)));
        }

        // Filtre d'état de paiement calculé en SQL (sous-requête sur la somme des paiements)
        $paidSql = '(SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.reservation_id = reservations.id)';
        match ($this->state) {
            'unpaid' => $query->whereRaw("{$paidSql} = 0")->where('total_amount', '>', 0),
            'partial' => $query->whereRaw("{$paidSql} > 0")->whereRaw("{$paidSql} < total_amount"),
            'paid' => $query->whereRaw("{$paidSql} >= total_amount"),
            default => null,
        };

        return view('livewire.invoices.index', ['invoices' => $query->latest('id')->paginate(12)]);
    }
}
