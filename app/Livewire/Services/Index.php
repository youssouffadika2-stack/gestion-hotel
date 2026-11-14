<?php

namespace App\Livewire\Services;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Department;
use App\Models\DepartmentClosure;
use App\Models\Sale;
use App\Models\Service;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Services')]
class Index extends Component
{
    use InteractsWithHotel;

    // --- Services de l'hôtel (Réception, Restaurant, Bar…) ---
    public bool $showDept = false;
    public ?int $deptId = null;
    public string $dept_name = '';
    public string $dept_type = 'restaurant';

    // --- Catalogue (plats, boissons, prestations) ---
    public bool $showForm = false;
    public ?int $editingId = null;
    public ?int $department_id = null;
    public string $name = '';
    public int|string $price = '';
    public bool $active = true;

    protected array $validationAttributes = [
        'dept_name' => 'nom du service', 'dept_type' => 'type', 'department_id' => 'service', 'name' => 'nom', 'price' => 'prix',
    ];

    public function mount(): void
    {
        $this->authorizeHotel('manageCatalog');
    }

    /* ---------------- Services de l'hôtel ---------------- */

    public function openDept(): void
    {
        $this->authorizeHotel('manageCatalog');
        $this->resetValidation();
        $this->reset(['deptId', 'dept_name']);
        $this->dept_type = 'restaurant';
        $this->showDept = true;
    }

    public function editDept(int $id): void
    {
        $this->authorizeHotel('manageCatalog');
        $d = $this->department($id);
        $this->resetValidation();
        $this->deptId = $d->id;
        $this->dept_name = $d->name;
        $this->dept_type = $d->type;
        $this->showDept = true;
    }

    public function saveDept(): void
    {
        $hotel = $this->authorizeHotel('manageCatalog');

        $data = $this->validate([
            'dept_name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('departments', 'name')->where('hotel_id', $hotel->id)->ignore($this->deptId)],
            'dept_type' => 'required|in:'.implode(',', array_keys(Department::TYPES)),
        ]);

        if ($this->deptId) {
            $d = $this->department($this->deptId);
            // Le type « Réception » est réservé (paiements des séjours) : on ne le change pas.
            $type = $d->isReception() ? 'reception' : $data['dept_type'];
            abort_if(! $d->isReception() && $type === 'reception', 422);
            $d->update(['name' => trim($data['dept_name']), 'type' => $type]);
        } else {
            abort_if($data['dept_type'] === 'reception', 422);
            Department::create([
                'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id,
                'name' => trim($data['dept_name']), 'type' => $data['dept_type'], 'active' => true,
            ]);
        }

        $this->showDept = false;
        $this->toast('Service enregistré.');
    }

    public function toggleDept(int $id): void
    {
        $this->authorizeHotel('manageCatalog');
        $d = $this->department($id);

        if ($d->isReception()) {
            $this->toast('La Réception ne peut pas être désactivée.', 'error');

            return;
        }
        $d->update(['active' => ! $d->active]);
    }

    public function deleteDept(int $id): void
    {
        $hotel = $this->authorizeHotel('manageCatalog');
        $d = $this->department($id);

        if ($d->isReception()) {
            $this->toast('La Réception ne peut pas être supprimée.', 'error');

            return;
        }
        if (Sale::where('hotel_id', $hotel->id)->where('department_id', $d->id)->exists()
            || DepartmentClosure::where('hotel_id', $hotel->id)->where('department_id', $d->id)->exists()) {
            $this->toast('Ce service a un historique de ventes : désactivez-le plutôt.', 'error');

            return;
        }

        $d->delete();
        $this->toast('Service supprimé.');
    }

    /* ---------------- Catalogue ---------------- */

    public function openCreate(?int $departmentId = null): void
    {
        $this->authorizeHotel('manageCatalog');
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'price']);
        $this->department_id = $departmentId && $this->department($departmentId) ? $departmentId : null;
        $this->active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeHotel('manageCatalog');
        $s = $this->service($id);
        $this->resetValidation();
        $this->editingId = $s->id;
        $this->department_id = $s->department_id;
        $this->name = $s->name;
        $this->price = $s->price;
        $this->active = $s->active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('manageCatalog');
        $data = $this->validate([
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('hotel_id', $hotel->id)],
            'name' => 'required|string|min:2|max:100',
            'price' => 'required|integer|min:0|max:100000000',
            'active' => 'boolean',
        ]);

        if ($this->editingId) {
            $this->service($this->editingId)->update($data);
        } else {
            Service::create($data + ['tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id]);
        }
        $this->showForm = false;
        $this->toast('Article enregistré.');
    }

    public function toggle(int $id): void
    {
        $this->authorizeHotel('manageCatalog');
        $s = $this->service($id);
        $s->update(['active' => ! $s->active]);
    }

    public function delete(int $id): void
    {
        $this->authorizeHotel('manageCatalog');
        $this->service($id)->delete();
        $this->toast('Article supprimé.');
    }

    private function department(int $id): Department
    {
        return Department::where('hotel_id', $this->hotel()->id)->findOrFail($id);
    }

    private function service(int $id): Service
    {
        return Service::where('hotel_id', $this->hotel()->id)->findOrFail($id);
    }

    public function render()
    {
        $hotel = $this->hotel();

        $departments = Department::where('hotel_id', $hotel->id)
            ->withCount('users')
            ->with(['services' => fn ($q) => $q->orderBy('name')])
            ->orderByRaw("CASE WHEN type = 'reception' THEN 0 ELSE 1 END")->orderBy('name')->get();

        return view('livewire.services.index', [
            'departments' => $departments,
            'types' => Department::TYPES,
            'orphans' => Service::where('hotel_id', $hotel->id)->whereNull('department_id')->orderBy('name')->get(),
        ]);
    }
}
