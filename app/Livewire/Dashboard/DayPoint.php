<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Services\DayClosingService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/** Carte « Point de la journée » du tableau de bord. */
class DayPoint extends Component
{
    use InteractsWithHotel;

    public function placeholder()
    {
        return view('livewire.dashboard.placeholder', ['height' => 'h-48']);
    }

    public function render()
    {
        $hotel = $this->hotel();
        abort_unless(Gate::allows('viewSales', $hotel), 403);

        $service = app(DayClosingService::class);
        $summary = $service->summary($hotel, $service->today());

        $seesAll = Gate::allows('viewFinance', $hotel);
        $rows = collect($summary['rows']);
        if (! $seesAll) {
            $rows = $rows->filter(fn ($r) => Gate::allows('operate', [$hotel, $r['department']]))->values();
        }

        return view('livewire.dashboard.day-point', [
            'summary' => $summary,
            'rows' => $rows,
            'seesAll' => $seesAll,
            'canCloseDay' => Gate::allows('closeDay', $hotel),
        ]);
    }
}
