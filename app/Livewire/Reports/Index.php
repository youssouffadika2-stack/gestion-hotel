<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Reservation;
use App\Services\DashboardService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Rapports et statistiques')]
class Index extends Component
{
    use InteractsWithHotel;

    public const PERIODS = ['7d' => '7 jours', '30d' => '30 jours', '12m' => '12 mois'];

    #[Url(as: 'periode')]
    public string $period = '30d';

    public function mount(): void
    {
        $this->authorizeHotel('viewFinance');
        $this->period = self::clean($this->period);
    }

    public static function clean(?string $period): string
    {
        return array_key_exists((string) $period, self::PERIODS) ? $period : '30d';
    }

    public function updatedPeriod(): void
    {
        $this->period = self::clean($this->period);

        // Le graphique est en wire:ignore : on lui envoie les nouvelles données.
        $this->dispatch('dashboard-updated', charts: ['revenue' => app(DashboardService::class)->revenueSeries($this->hotel(), $this->period)]);
    }

    /** Données du rapport (réutilisées par l'export CSV). */
    public static function build($hotel, string $period): array
    {
        $service = app(DashboardService::class);
        $revenue = $service->revenueSeries($hotel, $period);
        $expenses = $service->expenseSeries($hotel, $period);
        [$start, $end] = $service->range($period);

        $rows = [];
        foreach ($revenue['labels'] as $i => $label) {
            $rows[] = [
                'label' => $label, 'revenue' => $revenue['values'][$i], 'expenses' => $expenses['values'][$i] ?? 0,
                'net' => $revenue['values'][$i] - ($expenses['values'][$i] ?? 0),
            ];
        }

        $stays = Reservation::where('hotel_id', $hotel->id)->whereBetween('created_at', [$start, $end]);
        $byStatus = (clone $stays)->select('status', DB::raw('COUNT(*) n'))->groupBy('status')->pluck('n', 'status');
        $count = (int) $byStatus->sum();

        $totalRevenue = array_sum($revenue['values']);
        $totalExpenses = array_sum($expenses['values']);

        return [
            'rows' => $rows,
            'totals' => ['revenue' => $totalRevenue, 'expenses' => $totalExpenses, 'net' => $totalRevenue - $totalExpenses],
            'reservations' => $count,
            'cancel_rate' => $count ? (int) round(($byStatus[Reservation::CANCELLED] ?? 0) / $count * 100) : 0,
            'avg_basket' => $count ? (int) round((clone $stays)->where('status', '!=', Reservation::CANCELLED)->avg('total_amount')) : 0,
            'rooms' => $service->roomCounts($hotel),
            'revenueChart' => $revenue,
            'expenseChart' => $expenses,
        ];
    }

    public function render()
    {
        $hotel = $this->authorizeHotel('viewFinance');
        $report = self::build($hotel, $this->period);
        $rooms = $report['rooms'];
        $roomTotal = array_sum($rooms);

        return view('livewire.reports.index', [
            'report' => $report,
            'occupancy' => $roomTotal ? (int) round($rooms['occupied'] / $roomTotal * 100) : 0,
            'periods' => self::PERIODS,
        ]);
    }
}
