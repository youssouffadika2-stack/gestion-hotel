<?php

namespace App\Livewire\Dashboard;

use App\Services\CurrentHotel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HotelSwitcher extends Component
{
    public function switchTo(int $hotelId)
    {
        // Revalidé côté serveur : 403 si l'utilisateur n'a pas accès à cet hôtel.
        app(CurrentHotel::class)->switchTo($hotelId);

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.dashboard.hotel-switcher', [
            'current' => app(CurrentHotel::class)->get(),
            'hotels' => Auth::user()->hotels()->where('hotels.status', 'active')->orderBy('hotels.name')->get(['hotels.id', 'hotels.name', 'hotels.city']),
        ]);
    }
}
