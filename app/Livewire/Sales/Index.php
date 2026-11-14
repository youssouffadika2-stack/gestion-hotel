<?php

namespace App\Livewire\Sales;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Department;
use App\Models\Sale;
use App\Models\Service;
use App\Services\DayClosingService;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Ventes des services')]
class Index extends Component
{
    use InteractsWithHotel;
    use WithPagination;

    #[Url(as: 'service', except: '')]
    public string $departmentFilter = '';

    #[Url(as: 'date', except: '')]
    public string $date = '';

    public bool $showForm = false;
    public ?int $department_id = null;
    public ?int $service_id = null;
    public string $label = '';
    public int|string $quantity = 1;
    public int|string $unit_price = '';
    public string $method = 'cash';

    protected array $validationAttributes = [
        'department_id' => 'service', 'service_id' => 'article', 'label' => 'désignation',
        'quantity' => 'quantité', 'unit_price' => 'prix unitaire', 'method' => 'mode de paiement',
    ];

    public function mount(): void
    {
        $this->authorizeHotel('viewSales');
        $this->date = $this->cleanDate($this->date);

        if (request()->boolean('nouveau')) {
            $this->openCreate(request()->integer('service') ?: null);
        }
    }

    private function cleanDate(?string $date): string
    {
        $ok = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false && $date <= today()->toDateString();

        return $ok ? $date : today()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->date = $this->cleanDate($this->date);
        $this->resetPage();
    }

    public function updatingDepartmentFilter(): void { $this->resetPage(); }

    /** Services dans lesquels l'utilisateur peut vendre. */
    private function operable()
    {
        $hotel = $this->hotel();

        return Department::where('hotel_id', $hotel->id)->where('active', true)->orderBy('name')->get()
            ->filter(fn ($d) => Gate::allows('operate', [$hotel, $d]))->values();
    }

    /** Services dont l'utilisateur peut consulter les ventes. */
    private function visible()
    {
        $hotel = $this->hotel();

        return Gate::allows('viewFinance', $hotel)
            ? Department::where('hotel_id', $hotel->id)->orderBy('name')->get()
            : $this->operable();
    }

    public function openCreate(?int $departmentId = null): void
    {
        $this->hotel();
        $operable = $this->operable();
        abort_if($operable->isEmpty(), 403);

        $this->resetValidation();
        $this->reset(['service_id', 'label', 'unit_price']);
        $this->quantity = 1;
        $this->method = 'cash';
        $this->department_id = $operable->firstWhere('id', $departmentId)?->id ?? $operable->first()->id;
        $this->showForm = true;
    }

    public function updatedDepartmentId(): void
    {
        $this->reset(['service_id', 'label', 'unit_price']);
    }

    public function updatedServiceId(): void
    {
        $service = $this->service_id ? Service::where('hotel_id', $this->hotel()->id)->where('department_id', $this->department_id)->find($this->service_id) : null;
        if ($service) {
            $this->label = $service->name;
            $this->unit_price = $service->price;
        }
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('viewSales');

        $data = $this->validate([
            'department_id' => 'required|integer',
            'service_id' => 'nullable|integer',
            'label' => 'required|string|min:2|max:150',
            'quantity' => 'required|integer|min:1|max:10000',
            'unit_price' => 'required|integer|min:1|max:100000000',
            'method' => 'required|in:'.implode(',', array_keys(DayClosingService::METHOD_LABELS)),
        ]);

        $department = $this->operable()->firstWhere('id', $data['department_id']);
        abort_unless($department, 403);

        $closing = app(DayClosingService::class);
        if ($closing->isLocked($hotel, $department, $closing->today())) {
            throw ValidationException::withMessages(['department_id' => "Le point de « {$department->name} » est clôturé pour aujourd'hui : plus de vente possible."]);
        }

        $serviceId = $data['service_id']
            ? Service::where('hotel_id', $hotel->id)->where('department_id', $department->id)->whereKey($data['service_id'])->value('id')
            : null;

        Sale::create([
            'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'department_id' => $department->id,
            'service_id' => $serviceId, 'label' => trim($data['label']), 'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'], 'amount' => $data['quantity'] * $data['unit_price'], // calculé côté serveur
            'method' => $data['method'], 'sold_at' => now(), 'business_date' => $closing->today(), 'created_by' => auth()->id(),
        ]);

        $this->dataChanged();
        $this->showForm = false;
        $this->toast('Vente enregistrée : '.Money::fcfa($data['quantity'] * $data['unit_price']).'.');
    }

    public function delete(int $id): void
    {
        $hotel = $this->authorizeHotel('viewSales');
        $sale = Sale::where('hotel_id', $hotel->id)->with('department')->findOrFail($id);

        abort_unless(Gate::allows('operate', [$hotel, $sale->department]), 403);

        if (app(DayClosingService::class)->isLocked($hotel, $sale->department, $sale->business_date->toDateString())) {
            $this->toast('Le point de ce service est clôturé : suppression impossible.', 'error');

            return;
        }

        $sale->delete();
        $this->dataChanged();
        $this->toast('Vente supprimée.');
    }

    public function render()
    {
        $hotel = $this->authorizeHotel('viewSales');
        $closing = app(DayClosingService::class);
        $visible = $this->visible();
        $ids = $visible->pluck('id');

        $query = Sale::where('hotel_id', $hotel->id)->whereIn('department_id', $ids)
            ->where('business_date', $this->date)->with(['department:id,name', 'author:id,name']);

        if ($this->departmentFilter !== '' && $ids->contains((int) $this->departmentFilter)) {
            $query->where('department_id', (int) $this->departmentFilter);
        }

        $operable = $this->operable();
        $totals = Sale::where('hotel_id', $hotel->id)->whereIn('department_id', $ids)->where('business_date', $this->date)
            ->selectRaw('department_id, SUM(amount) as total, COUNT(*) as n')->groupBy('department_id')->get()->keyBy('department_id');

        return view('livewire.sales.index', [
            'sales' => $query->latest('sold_at')->latest('id')->paginate(15),
            'visible' => $visible,
            'operable' => $operable,
            'totals' => $totals,
            'catalog' => $this->showForm
                ? Service::where('hotel_id', $hotel->id)->where('department_id', $this->department_id)->where('active', true)->orderBy('name')->get(['id', 'name', 'price'])
                : collect(),
            'methods' => DayClosingService::METHOD_LABELS,
            'isToday' => $this->date === $closing->today(),
            'lockedIds' => $visible->filter(fn ($d) => $closing->isLocked($hotel, $d, $this->date))->pluck('id')->all(),
        ]);
    }
}
