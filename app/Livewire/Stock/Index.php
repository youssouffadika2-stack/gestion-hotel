<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\StockItem;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Gestion des stocks')]
class Index extends Component
{
    use InteractsWithHotel;

    public bool $showForm = false;
    public bool $showMove = false;
    public ?int $editingId = null;
    public ?int $moveId = null;
    public string $name = '';
    public string $unit = 'unité';
    public int|string $quantity = 0;
    public int|string $alert_threshold = 0;
    public int|string $moveQty = '';
    public string $moveType = 'in'; // in | out

    protected array $validationAttributes = [
        'name' => 'nom', 'unit' => 'unité', 'quantity' => 'quantité', 'alert_threshold' => "seuil d'alerte", 'moveQty' => 'quantité',
    ];

    public function mount(): void
    {
        $this->authorizeHotel('manageOperations');
    }

    public function openCreate(): void
    {
        $this->authorizeHotel('manageOperations');
        $this->resetValidation();
        $this->reset(['editingId', 'name']);
        $this->unit = 'unité';
        $this->quantity = 0;
        $this->alert_threshold = 0;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeHotel('manageOperations');
        $i = $this->item($id);
        $this->resetValidation();
        $this->editingId = $i->id;
        $this->name = $i->name;
        $this->unit = $i->unit;
        $this->quantity = $i->quantity;
        $this->alert_threshold = $i->alert_threshold;
        $this->showForm = true;
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $data = $this->validate([
            'name' => 'required|string|min:2|max:100', 'unit' => 'required|string|max:20',
            'quantity' => 'required|integer|min:0|max:10000000', 'alert_threshold' => 'required|integer|min:0|max:10000000',
        ]);

        if ($this->editingId) {
            $this->item($this->editingId)->update($data);
        } else {
            StockItem::create($data + ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id]);
        }
        $this->showForm = false;
        $this->toast('Article enregistré.');
    }

    public function openMove(int $id, string $type): void
    {
        $this->authorizeHotel('manageOperations');
        $this->item($id);
        $this->resetValidation();
        $this->moveId = $id;
        $this->moveType = $type === 'out' ? 'out' : 'in';
        $this->moveQty = '';
        $this->showMove = true;
    }

    public function saveMove(): void
    {
        $hotel = $this->authorizeHotel('manageOperations');
        $data = $this->validate(['moveQty' => 'required|integer|min:1|max:10000000']);

        // Mise à jour atomique : un stock ne peut jamais devenir négatif.
        $query = StockItem::where('hotel_id', $hotel->id)->whereKey($this->moveId);
        if ($this->moveType === 'out') {
            $ok = $query->where('quantity', '>=', $data['moveQty'])->decrement('quantity', $data['moveQty']);
            if (! $ok) {
                $this->addError('moveQty', 'Stock insuffisant pour cette sortie.');

                return;
            }
        } else {
            $query->increment('quantity', $data['moveQty']);
        }

        $this->showMove = false;
        $this->toast($this->moveType === 'out' ? 'Sortie de stock enregistrée.' : 'Entrée de stock enregistrée.');
    }

    public function delete(int $id): void
    {
        $this->authorizeHotel('manageOperations');
        $this->item($id)->delete();
        $this->toast('Article supprimé.');
    }

    private function item(int $id): StockItem
    {
        return StockItem::where('hotel_id', $this->hotel()->id)->findOrFail($id);
    }

    public function render()
    {
        $hotel = $this->hotel();

        return view('livewire.stock.index', [
            'items' => StockItem::where('hotel_id', $hotel->id)->orderBy('name')->get(),
            'canManage' => auth()->user()->can('manageOperations', $hotel),
        ]);
    }
}
