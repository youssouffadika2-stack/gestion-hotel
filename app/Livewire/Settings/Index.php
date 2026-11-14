<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\InteractsWithHotel;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title("Paramètres de l'hôtel")]
class Index extends Component
{
    use InteractsWithHotel;

    public string $name = '';
    public string $city = '';

    protected array $validationAttributes = ['name' => "nom de l'hôtel", 'city' => 'ville'];

    public function mount(): void
    {
        $hotel = $this->authorizeHotel('manageHotel');
        $this->name = $hotel->name;
        $this->city = (string) $hotel->city;
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('manageHotel');

        $data = $this->validate(['name' => 'required|string|min:2|max:120', 'city' => 'nullable|string|max:80']);
        $hotel->update(['name' => trim($data['name']), 'city' => trim((string) $data['city']) ?: null]);

        session()->flash('toast', 'Paramètres enregistrés.');
        $this->redirectRoute('settings.index'); // rafraîchit le nom affiché dans la barre supérieure
    }

    public function render()
    {
        return view('livewire.settings.index', ['hotel' => $this->hotel()]);
    }
}
