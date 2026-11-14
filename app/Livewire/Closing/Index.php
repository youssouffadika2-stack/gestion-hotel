<?php

namespace App\Livewire\Closing;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\DayClosure;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\DayClosingService;
use DomainException;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Point du jour')]
class Index extends Component
{
    use InteractsWithHotel;

    /** @var array<int, int|string|null> espèces comptées, par service */
    public array $cash = [];

    /** @var array<int, string|null> */
    public array $notes = [];

    public string $dayNote = '';

    public function mount(): void
    {
        $this->authorizeHotel('viewSales');
    }

    public function closeDepartment(int $departmentId): void
    {
        $hotel = $this->authorizeHotel('viewSales');
        $service = app(DayClosingService::class);
        $department = $service->departments($hotel, false)->firstWhere('id', $departmentId);

        abort_unless($department && Gate::allows('operate', [$hotel, $department]), 403);

        $counted = $this->cash[$departmentId] ?? null;
        if ($counted !== null && $counted !== '' && (! is_numeric($counted) || (int) $counted < 0 || (int) $counted > 1000000000)) {
            $this->addError("cash.$departmentId", 'Montant invalide.');

            return;
        }

        try {
            $service->closeDepartment(
                $hotel, $department, $service->today(),
                ($counted === null || $counted === '') ? null : (int) $counted,
                $this->notes[$departmentId] ?? null, auth()->id(),
            );
            $this->resetErrorBag();
            $this->toast("Point de « {$department->name} » clôturé.");
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    public function reopenDepartment(int $departmentId): void
    {
        $hotel = $this->authorizeHotel('closeDay'); // réservé à l'administrateur / gérant
        $service = app(DayClosingService::class);
        $department = $service->departments($hotel, false)->firstWhere('id', $departmentId);
        abort_unless($department, 404);

        try {
            $service->reopenDepartment($hotel, $department, $service->today(), auth()->id());
            $this->toast("Point de « {$department->name} » rouvert.");
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    public function closeDay(): void
    {
        $hotel = $this->authorizeHotel('closeDay');
        $this->validate(['dayNote' => 'nullable|string|max:500']);
        $service = app(DayClosingService::class);

        try {
            $service->closeDay($hotel, $service->today(), $this->dayNote, auth()->id());
            $this->dayNote = '';
            $this->dataChanged();
            $this->toast('Journée clôturée. Bonne soirée !');
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    public function reopenDay(): void
    {
        $hotel = $this->authorizeHotel('manageHotel'); // seul l'administrateur peut rouvrir une journée
        $service = app(DayClosingService::class);
        $service->reopenDay($hotel, $service->today(), auth()->id());
        $this->toast('Journée rouverte.');
    }

    public function render()
    {
        $hotel = $this->authorizeHotel('viewSales');
        $service = app(DayClosingService::class);
        $date = $service->today();
        $summary = $service->summary($hotel, $date);

        $seesAll = Gate::allows('viewFinance', $hotel);
        $rows = collect($summary['rows'])->map(function ($row) use ($hotel) {
            $row['can_close'] = Gate::allows('operate', [$hotel, $row['department']]);

            return $row;
        });
        if (! $seesAll) {
            $rows = $rows->filter(fn ($r) => $r['can_close'])->values();
        }

        // Alerte : la veille a eu de l'activité mais n'a pas été clôturée.
        $yesterday = today()->subDay()->toDateString();
        $yesterdayOpen = ! DayClosure::where('hotel_id', $hotel->id)->where('business_date', $yesterday)->exists()
            && (Sale::where('hotel_id', $hotel->id)->where('business_date', $yesterday)->exists()
                || Payment::where('hotel_id', $hotel->id)->whereDate('paid_at', $yesterday)->exists());

        return view('livewire.closing.index', [
            'summary' => $summary,
            'rows' => $rows,
            'date' => $date,
            'methods' => DayClosingService::METHOD_LABELS,
            'canCloseDay' => Gate::allows('closeDay', $hotel),
            'isOwner' => Gate::allows('manageHotel', $hotel),
            'yesterdayOpen' => $yesterdayOpen,
        ]);
    }
}
