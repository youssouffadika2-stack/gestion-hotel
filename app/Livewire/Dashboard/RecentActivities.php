<?php

namespace App\Livewire\Dashboard;

use App\Services\CurrentHotel;
use App\Services\DashboardService;
use Livewire\Component;

class RecentActivities extends Component
{
    public function placeholder()
    {
        return view('livewire.dashboard.placeholder', ['height' => 'h-72']);
    }

    public function render()
    {
        $hotel = app(CurrentHotel::class)->get();
        abort_unless($hotel, 403);

        return view('livewire.dashboard.recent-activities', [
            'activities' => app(DashboardService::class)->recentActivities($hotel),
        ]);
    }
}
