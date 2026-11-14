<?php

namespace App\Livewire\Expenses;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Expense;
use App\Services\DashboardService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Dépenses')]
class Index extends Component
{
    use InteractsWithHotel;
    use WithPagination;

    public const CATEGORIES = [
        'charges' => 'Charges (eau, électricité, internet)', 'personnel' => 'Personnel', 'stock' => 'Achats et stock',
        'travaux' => 'Travaux et maintenance', 'marketing' => 'Marketing', 'autre' => 'Autre',
    ];

    #[Url(as: 'periode', except: '30d')]
    public string $period = '30d';

    #[Url(as: 'categorie', except: '')]
    public string $category = '';

    public bool $showForm = false;
    public string $label = '';
    public string $expCategory = 'charges';
    public int|string $amount = '';
    public string $spent_at = '';

    protected array $validationAttributes = ['label' => 'libellé', 'expCategory' => 'catégorie', 'amount' => 'montant', 'spent_at' => 'date'];

    public function mount(): void
    {
        $this->authorizeHotel('viewFinance');
        $this->period = DashboardService::normalizePeriod($this->period);

        if (request()->boolean('nouveau')) {
            $this->openCreate();
        }
    }

    public function updatingPeriod(): void { $this->resetPage(); }
    public function updatingCategory(): void { $this->resetPage(); }

    public function updatedPeriod(): void
    {
        $this->period = DashboardService::normalizePeriod($this->period);
    }

    public function openCreate(): void
    {
        $this->authorizeHotel('viewFinance');
        $this->resetValidation();
        $this->reset(['label', 'amount']);
        $this->expCategory = 'charges';
        $this->spent_at = today()->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('viewFinance');

        $data = $this->validate([
            'label' => 'required|string|min:2|max:150',
            'expCategory' => 'required|in:'.implode(',', array_keys(self::CATEGORIES)),
            'amount' => 'required|integer|min:1|max:1000000000',
            'spent_at' => 'required|date|before_or_equal:today',
        ]);

        Expense::create([
            'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id,
            'label' => trim($data['label']), 'category' => $data['expCategory'],
            'amount' => $data['amount'], 'spent_at' => $data['spent_at'], 'created_by' => auth()->id(),
        ]);

        $this->dataChanged();
        $this->showForm = false;
        $this->toast('Dépense enregistrée.');
    }

    public function delete(int $id): void
    {
        $hotel = $this->authorizeHotel('viewFinance');
        Expense::where('hotel_id', $hotel->id)->findOrFail($id)->delete();
        $this->dataChanged();
        $this->toast('Dépense supprimée.');
    }

    public function render()
    {
        $hotel = $this->authorizeHotel('viewFinance');
        [$start, $end] = app(DashboardService::class)->range($this->period);

        $query = Expense::where('hotel_id', $hotel->id)
            ->whereBetween('spent_at', [$start->toDateString(), $end->toDateString()]);
        if (array_key_exists($this->category, self::CATEGORIES)) {
            $query->where('category', $this->category);
        }

        return view('livewire.expenses.index', [
            'expenses' => (clone $query)->latest('spent_at')->latest('id')->paginate(12),
            'total' => (int) (clone $query)->sum('amount'),
            'categories' => self::CATEGORIES,
            'periods' => DashboardService::PERIOD_LABELS,
        ]);
    }
}
