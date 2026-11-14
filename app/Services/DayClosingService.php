<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DayClosure;
use App\Models\Department;
use App\Models\DepartmentClosure;
use App\Models\Expense;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Sale;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Point de la journée.
 *
 *  1. Chaque service (Réception, Restaurant, Bar…) clôture SON point.
 *  2. Le point TOTAL de l'hôtel n'est possible que lorsque TOUS les services actifs ont clôturé.
 *
 * Un service clôturé (ou une journée clôturée) est verrouillé : plus de vente ni d'encaissement ce jour-là.
 */
class DayClosingService
{
    public const METHOD_LABELS = ['cash' => 'Espèces', 'mobile_money' => 'Mobile Money', 'card' => 'Carte bancaire', 'transfer' => 'Virement'];

    public function today(): string
    {
        return today()->toDateString();
    }

    /** Services de l'hôtel (actifs par défaut), la Réception en premier. */
    public function departments(Hotel $hotel, bool $onlyActive = true)
    {
        return Department::where('hotel_id', $hotel->id)
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderByRaw("CASE WHEN type = 'reception' THEN 0 ELSE 1 END")->orderBy('name')->get();
    }

    /** @return array{total:int, operations:int, by_method:array<string,int>, cash:int} */
    public function departmentTotals(Hotel $hotel, Department $department, string $date): array
    {
        $byMethod = array_fill_keys(array_keys(self::METHOD_LABELS), 0);
        $operations = 0;

        $sales = Sale::where('hotel_id', $hotel->id)->where('department_id', $department->id)->where('business_date', $date)
            ->select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as n'))->groupBy('method')->get();

        foreach ($sales as $row) {
            $byMethod[$row->method] = ($byMethod[$row->method] ?? 0) + (int) $row->total;
            $operations += (int) $row->n;
        }

        // Les paiements de séjour (chambres) appartiennent à la Réception.
        if ($department->isReception()) {
            $payments = Payment::where('hotel_id', $hotel->id)->whereDate('paid_at', $date)
                ->select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as n'))->groupBy('method')->get();

            foreach ($payments as $row) {
                $byMethod[$row->method] = ($byMethod[$row->method] ?? 0) + (int) $row->total;
                $operations += (int) $row->n;
            }
        }

        return ['total' => array_sum($byMethod), 'operations' => $operations, 'by_method' => $byMethod, 'cash' => $byMethod['cash']];
    }

    /**
     * Vue d'ensemble de la journée.
     *
     * @return array{rows: array<int,array>, all_closed: bool, pending: array<int,string>, day: ?DayClosure, revenue:int, expenses:int}
     */
    public function summary(Hotel $hotel, string $date): array
    {
        $closures = DepartmentClosure::where('hotel_id', $hotel->id)->where('business_date', $date)
            ->with('closer:id,name')->get()->keyBy('department_id');
        $day = DayClosure::where('hotel_id', $hotel->id)->where('business_date', $date)->with('closer:id,name')->first();

        $rows = [];
        $pending = [];
        $revenue = 0;

        foreach ($this->departments($hotel) as $department) {
            $closure = $closures->get($department->id);
            $totals = $closure
                ? ['total' => $closure->total, 'operations' => $closure->operations, 'by_method' => $this->departmentTotals($hotel, $department, $date)['by_method'], 'cash' => $closure->cash_expected]
                : $this->departmentTotals($hotel, $department, $date);

            $rows[] = ['department' => $department, 'totals' => $totals, 'closure' => $closure];
            $revenue += $totals['total'];
            if (! $closure) {
                $pending[] = $department->name;
            }
        }

        return [
            'rows' => $rows,
            'pending' => $pending,
            'all_closed' => $rows !== [] && $pending === [],
            'day' => $day,
            'revenue' => $revenue,
            'expenses' => (int) Expense::where('hotel_id', $hotel->id)->whereDate('spent_at', $date)->sum('amount'),
        ];
    }

    /** Vrai si les ventes / encaissements du service sont verrouillés pour cette date. */
    public function isLocked(Hotel $hotel, Department $department, string $date): bool
    {
        return DepartmentClosure::where('hotel_id', $hotel->id)->where('department_id', $department->id)->where('business_date', $date)->exists()
            || DayClosure::where('hotel_id', $hotel->id)->where('business_date', $date)->exists();
    }

    public function receptionDepartment(Hotel $hotel): ?Department
    {
        return Department::where('hotel_id', $hotel->id)->where('type', 'reception')->first();
    }

    /** @throws DomainException */
    public function closeDepartment(Hotel $hotel, Department $department, string $date, ?int $cashCounted, ?string $note, ?int $userId): DepartmentClosure
    {
        return DB::transaction(function () use ($hotel, $department, $date, $cashCounted, $note, $userId) {
            // Verrou de l'hôtel : sérialise les clôtures concurrentes.
            Hotel::whereKey($hotel->id)->lockForUpdate()->first();

            if ($this->isLocked($hotel, $department, $date)) {
                throw new DomainException("Le point de « {$department->name} » est déjà clôturé pour cette journée.");
            }

            $totals = $this->departmentTotals($hotel, $department, $date);

            $closure = DepartmentClosure::create([
                'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'department_id' => $department->id,
                'business_date' => $date, 'total' => $totals['total'], 'cash_expected' => $totals['cash'],
                'cash_counted' => $cashCounted, 'operations' => $totals['operations'],
                'note' => $note ? mb_substr(trim($note), 0, 500) : null,
                'closed_by' => $userId, 'closed_at' => now(),
            ]);

            $this->log($hotel, $userId, "Point de « {$department->name} » clôturé (".number_format($totals['total'], 0, ',', ' ').' FCFA)');

            return $closure;
        });
    }

    /** Rouvre le point d'un service (impossible si la journée entière est clôturée). */
    public function reopenDepartment(Hotel $hotel, Department $department, string $date, ?int $userId): void
    {
        DB::transaction(function () use ($hotel, $department, $date, $userId) {
            Hotel::whereKey($hotel->id)->lockForUpdate()->first();

            if (DayClosure::where('hotel_id', $hotel->id)->where('business_date', $date)->exists()) {
                throw new DomainException('La journée est clôturée : rouvrez d\'abord le point total.');
            }

            $deleted = DepartmentClosure::where('hotel_id', $hotel->id)->where('department_id', $department->id)->where('business_date', $date)->delete();
            if ($deleted) {
                $this->log($hotel, $userId, "Point de « {$department->name} » rouvert");
            }
        });
    }

    /** @throws DomainException si un service n'a pas encore clôturé son point */
    public function closeDay(Hotel $hotel, string $date, ?string $note, ?int $userId): DayClosure
    {
        return DB::transaction(function () use ($hotel, $date, $note, $userId) {
            Hotel::whereKey($hotel->id)->lockForUpdate()->first();

            if (DayClosure::where('hotel_id', $hotel->id)->where('business_date', $date)->exists()) {
                throw new DomainException('La journée est déjà clôturée.');
            }

            $summary = $this->summary($hotel, $date);

            if ($summary['rows'] === []) {
                throw new DomainException("Aucun service actif : créez au moins la Réception.");
            }
            if ($summary['pending'] !== []) {
                throw new DomainException('Impossible de clôturer la journée : le point n\'est pas fait pour '.implode(', ', $summary['pending']).'.');
            }

            $closure = DayClosure::create([
                'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'business_date' => $date,
                'total_revenue' => $summary['revenue'], 'total_expenses' => $summary['expenses'],
                'note' => $note ? mb_substr(trim($note), 0, 500) : null, 'closed_by' => $userId, 'closed_at' => now(),
            ]);

            $this->log($hotel, $userId, 'Journée du '.date('d/m/Y', strtotime($date)).' clôturée ('.number_format($summary['revenue'], 0, ',', ' ').' FCFA)');
            app(DashboardService::class)->flush($hotel);

            return $closure;
        });
    }

    public function reopenDay(Hotel $hotel, string $date, ?int $userId): void
    {
        DB::transaction(function () use ($hotel, $date, $userId) {
            Hotel::whereKey($hotel->id)->lockForUpdate()->first();

            if (DayClosure::where('hotel_id', $hotel->id)->where('business_date', $date)->delete()) {
                $this->log($hotel, $userId, 'Journée du '.date('d/m/Y', strtotime($date)).' rouverte');
            }
        });
    }

    private function log(Hotel $hotel, ?int $userId, string $description): void
    {
        ActivityLog::create([
            'tenant_id' => $hotel->tenant_id, 'hotel_id' => $hotel->id, 'user_id' => $userId,
            'type' => 'reservation_updated', 'description' => $description,
        ]);
    }
}
