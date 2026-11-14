<?php

namespace App\Livewire\Dashboard;

use App\Services\CurrentHotel;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'periode')]
    public string $period = '30d';

    public function mount(): void
    {
        $this->period = DashboardService::normalizePeriod($this->period);
        abort_unless(app(CurrentHotel::class)->get(), 403);
    }

    public function setPeriod(string $period): void
    {
        // Liste blanche : la valeur vient du navigateur, on ne lui fait pas confiance.
        $this->period = DashboardService::normalizePeriod($period);
        $this->dispatch('dashboard-updated', charts: $this->charts());
    }

    /** Données des 3 graphiques. Recalculées à chaque appel (la mise en cache est dans stats()). */
    private function charts(): array
    {
        $hotel = app(CurrentHotel::class)->get();
        abort_unless($hotel, 403);

        $service = app(DashboardService::class);
        $rooms = $service->roomCounts($hotel);

        return [
            'revenue' => Gate::allows('viewFinance', $hotel) ? $service->revenueSeries($hotel, $this->period) : null,
            'occupancy' => [
                'labels' => ['Occupées', 'Disponibles', 'Maintenance / hors service'],
                'values' => [$rooms['occupied'], $rooms['available'], $rooms['maintenance']],
            ],
            'reservations' => (function () use ($service, $hotel) {
                $b = $service->reservationBreakdown($hotel, $this->period);

                return ['labels' => array_keys($b), 'values' => array_values($b)];
            })(),
        ];
    }

    public function render()
    {
        $hotel = app(CurrentHotel::class)->get();
        abort_unless($hotel, 403);

        $canViewFinance = Gate::allows('viewFinance', $hotel);

        return view('livewire.dashboard.index', [
            'hotel' => $hotel,
            'canViewFinance' => $canViewFinance,
            'stats' => app(DashboardService::class)->stats($hotel, $this->period, $canViewFinance),
            'charts' => $this->charts(),
            'periods' => DashboardService::PERIOD_LABELS,
        ]);
    }
}
