<?php

namespace App\Livewire\Payments;

use App\Livewire\Concerns\InteractsWithHotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Paiements')]
class Index extends Component
{
    use InteractsWithHotel;
    use WithPagination;

    public const METHODS = ['cash' => 'Espèces', 'mobile_money' => 'Mobile Money', 'card' => 'Carte bancaire', 'transfer' => 'Virement'];

    #[Url(as: 'methode', except: '')]
    public string $method = '';

    public bool $showForm = false;
    public ?int $reservation_id = null;
    public int|string $amount = '';
    public string $payMethod = 'cash';

    protected array $validationAttributes = ['reservation_id' => 'réservation', 'amount' => 'montant', 'payMethod' => 'mode de paiement'];

    public function mount(): void
    {
        $this->authorizeHotel('recordPayment');

        if (request()->boolean('nouveau')) {
            $this->openCreate(request()->integer('reservation') ?: null);
        }
    }

    public function updatingMethod(): void { $this->resetPage(); }

    public function openCreate(?int $reservationId = null): void
    {
        $this->authorizeHotel('recordPayment');
        $this->resetValidation();
        $this->reset(['reservation_id', 'amount']);
        $this->payMethod = 'cash';

        if ($reservationId && $this->payable()->firstWhere('id', $reservationId)) {
            $this->reservation_id = $reservationId;
            $this->fillBalance();
        }
        $this->showForm = true;
    }

    public function updatedReservationId(): void
    {
        $this->fillBalance();
    }

    private function fillBalance(): void
    {
        $r = $this->payable()->firstWhere('id', $this->reservation_id);
        $this->amount = $r ? $r->balance : '';
    }

    /** Réservations encaissables : non annulées avec un solde > 0. */
    private function payable()
    {
        return Reservation::where('hotel_id', $this->hotel()->id)
            ->where('status', '!=', Reservation::CANCELLED)
            ->with('guest:id,name')->withSum('payments as paid', 'amount')
            ->latest('id')->limit(300)->get()
            ->map(function ($r) {
                $r->balance = max(0, $r->total_amount - (int) $r->paid);

                return $r;
            })->filter(fn ($r) => $r->balance > 0)->values();
    }

    public function save(): void
    {
        $hotel = $this->authorizeHotel('recordPayment');

        $data = $this->validate([
            'reservation_id' => 'required|integer',
            'amount' => 'required|integer|min:1',
            'payMethod' => 'required|in:'.implode(',', array_keys(self::METHODS)),
        ]);

        DB::transaction(function () use ($hotel, $data) {
            // Verrou : deux encaissements simultanés ne peuvent pas dépasser le solde.
            $r = Reservation::where('hotel_id', $hotel->id)->lockForUpdate()->find($data['reservation_id']);
            if (! $r || $r->status === Reservation::CANCELLED) {
                throw ValidationException::withMessages(['reservation_id' => 'Réservation introuvable ou annulée.']);
            }

            // Les encaissements de séjour appartiennent à la Réception : point clôturé = caisse fermée.
            $closing = app(\App\Services\DayClosingService::class);
            $reception = $closing->receptionDepartment($hotel);
            if ($reception && $closing->isLocked($hotel, $reception, $closing->today())) {
                throw ValidationException::withMessages(['reservation_id' => "Le point de la Réception est clôturé pour aujourd'hui : plus d'encaissement possible."]);
            }

            $balance = $r->total_amount - (int) Payment::where('hotel_id', $hotel->id)->where('reservation_id', $r->id)->sum('amount');
            if ($data['amount'] > $balance) {
                throw ValidationException::withMessages(['amount' => 'Le montant dépasse le solde restant ('.Money::fcfa($balance).').']);
            }

            Payment::create([
                'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'reservation_id' => $r->id,
                'amount' => $data['amount'], 'method' => $data['payMethod'], 'paid_at' => now(), 'created_by' => auth()->id(),
            ]);

            $this->logActivity('payment_recorded', 'Paiement de '.Money::fcfa($data['amount'])." enregistré ({$r->code})");
        });

        $this->dataChanged();
        $this->showForm = false;
        $this->toast('Paiement enregistré.');
    }

    public function render()
    {
        $hotel = $this->hotel();

        $query = Payment::where('hotel_id', $hotel->id)->with('reservation:id,code,guest_id', 'reservation.guest:id,name');
        if (array_key_exists($this->method, self::METHODS)) {
            $query->where('method', $this->method);
        }

        return view('livewire.payments.index', [
            'payments' => $query->latest('paid_at')->latest('id')->paginate(12),
            'payable' => $this->showForm ? $this->payable() : collect(),
            'methods' => self::METHODS,
            'total' => (int) Payment::where('hotel_id', $hotel->id)->whereDate('paid_at', today())->sum('amount'),
        ]);
    }
}
