<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Calculs du tableau de bord. Aucune dépendance à Livewire.
 * Chaque requête filtre EXPLICITEMENT sur hotel_id (en plus du global scope).
 */
class DashboardService
{
    public const PERIODS = ['today', '7d', '30d', '12m'];

    public const PERIOD_LABELS = [
        'today' => "Aujourd'hui",
        '7d' => '7 jours',
        '30d' => '30 jours',
        '12m' => '12 mois',
    ];

    private const CACHE_SECONDS = 60;

    public static function normalizePeriod(?string $period): string
    {
        return in_array($period, self::PERIODS, true) ? $period : '30d';
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} période courante [début, fin] */
    public function range(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '12m' => [$now->subMonths(11)->startOfMonth(), $now->endOfDay()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} période précédente de même durée */
    private function previousRange(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $seconds = $end->diffInSeconds($start, true) + 1;

        return [$start->subSeconds($seconds), $start->subSecond()];
    }

    /** Cartes statistiques. $withFinance = false masque CA et dépenses. */
    public function stats(Hotel $hotel, string $period, bool $withFinance): array
    {
        return Cache::remember(
            "dashboard.stats.{$hotel->id}.{$period}.".(int) $withFinance,
            self::CACHE_SECONDS,
            fn () => $this->computeStats($hotel, $period, $withFinance),
        );
    }

    private function computeStats(Hotel $hotel, string $period, bool $withFinance): array
    {
        [$start, $end] = $this->range($period);
        $today = CarbonImmutable::today();

        $rooms = $this->roomCounts($hotel);
        $total = array_sum($rooms);

        $reservationsToday = Reservation::where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [$today, $today->endOfDay()])->count();
        $reservationsYesterday = Reservation::where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [$today->subDay(), $today->subDay()->endOfDay()])->count();

        $guestsPresent = Reservation::where('hotel_id', $hotel->id)
            ->where('status', Reservation::CHECKED_IN)->distinct()->count('guest_id');

        $stats = [
            'rooms_total' => $total,
            'rooms_available' => $rooms[Room::AVAILABLE],
            'rooms_available_pct' => $this->pct($rooms[Room::AVAILABLE], $total),
            'rooms_occupied' => $rooms[Room::OCCUPIED],
            'rooms_occupied_pct' => $this->pct($rooms[Room::OCCUPIED], $total),
            'reservations_today' => $reservationsToday,
            'reservations_diff' => $reservationsToday - $reservationsYesterday,
            'guests_present' => $guestsPresent,
            'revenue' => null,
            'revenue_variation' => null,
            'expenses' => null,
        ];

        if ($withFinance) {
            [$pStart, $pEnd] = $this->previousRange($start, $end);
            $revenue = $this->revenueBetween($hotel, $start, $end);
            $previous = $this->revenueBetween($hotel, $pStart, $pEnd);

            $stats['revenue'] = $revenue;
            $stats['revenue_variation'] = $previous > 0 ? round((($revenue - $previous) / $previous) * 100, 1) : null;
            $stats['expenses'] = (int) Expense::where('hotel_id', $hotel->id)
                ->whereBetween('spent_at', [$start->toDateString(), $end->toDateString()])->sum('amount');
        }

        return $stats;
    }

    /** @return array<string,int> nombre de chambres par statut (tous les statuts présents) */
    public function roomCounts(Hotel $hotel): array
    {
        $counts = Room::where('hotel_id', $hotel->id)
            ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->all();

        return [
            Room::AVAILABLE => (int) ($counts[Room::AVAILABLE] ?? 0),
            Room::OCCUPIED => (int) ($counts[Room::OCCUPIED] ?? 0),
            Room::MAINTENANCE => (int) (($counts[Room::MAINTENANCE] ?? 0) + ($counts[Room::OUT_OF_SERVICE] ?? 0)),
        ];
    }

    /** Répartition des réservations de la période, par statut. */
    public function reservationBreakdown(Hotel $hotel, string $period): array
    {
        [$start, $end] = $this->range($period);

        $counts = Reservation::where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [$start, $end])
            ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->all();

        // « Confirmées » regroupe confirmées + en séjour.
        return [
            'Confirmées' => (int) (($counts[Reservation::CONFIRMED] ?? 0) + ($counts[Reservation::CHECKED_IN] ?? 0)),
            'En attente' => (int) ($counts[Reservation::PENDING] ?? 0),
            'Annulées' => (int) ($counts[Reservation::CANCELLED] ?? 0),
            'Terminées' => (int) ($counts[Reservation::COMPLETED] ?? 0),
        ];
    }

    /** Invalide les statistiques en cache d'un hôtel (appelé après chaque écriture). */
    public function flush(Hotel $hotel): void
    {
        foreach (self::PERIODS as $period) {
            foreach ([0, 1] as $finance) {
                Cache::forget("dashboard.stats.{$hotel->id}.{$period}.{$finance}");
            }
        }
    }

    /** Série des dépenses (même format que revenueSeries ; pas de granularité horaire). */
    public function expenseSeries(Hotel $hotel, string $period): array
    {
        [$start, $end] = $this->range($period);
        $monthly = $period === '12m';

        $expr = $monthly ? 'month' : 'day';
        $rows = Expense::where('hotel_id', $hotel->id)
            ->whereBetween('spent_at', [$start->toDateString(), $end->toDateString()])
            ->select(DB::raw($this->bucketExpression($expr, 'spent_at').' as bucket'), DB::raw('SUM(amount) as total'))
            ->groupBy('bucket')->pluck('total', 'bucket')->all();

        $labels = [];
        $values = [];
        for ($d = $start; $d <= $end; $d = $d->modify($monthly ? '+1 month' : '+1 day')) {
            $labels[] = $monthly ? $d->translatedFormat('M Y') : $d->translatedFormat('d M');
            $values[] = (int) ($rows[$d->format($monthly ? 'Y-m' : 'Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Série du chiffre d'affaires : ['labels' => [...], 'values' => [...]] */
    public function revenueSeries(Hotel $hotel, string $period): array
    {
        [$start, $end] = $this->range($period);

        [$format, $php, $step] = match ($period) {
            'today' => ['hour', 'H', '1 hour'],
            '12m' => ['month', 'Y-m', '1 month'],
            default => ['day', 'Y-m-d', '1 day'],
        };

        // Chiffre d'affaires = encaissements des séjours + ventes des services (restaurant, bar…)
        $rows = Payment::where('hotel_id', $hotel->id)
            ->whereBetween('paid_at', [$start, $end])
            ->select(DB::raw($this->bucketExpression($format).' as bucket'), DB::raw('SUM(amount) as total'))
            ->groupBy('bucket')->pluck('total', 'bucket')->all();

        $salesRows = Sale::where('hotel_id', $hotel->id)
            ->whereBetween('sold_at', [$start, $end])
            ->select(DB::raw($this->bucketExpression($format, 'sold_at').' as bucket'), DB::raw('SUM(amount) as total'))
            ->groupBy('bucket')->pluck('total', 'bucket')->all();

        foreach ($salesRows as $bucket => $total) {
            $rows[$bucket] = (int) ($rows[$bucket] ?? 0) + (int) $total;
        }

        $labels = [];
        $values = [];
        for ($d = $start; $d <= $end; $d = $d->modify('+'.$step)) {
            $key = $d->format($php);
            $labels[] = match ($period) {
                'today' => $d->format('H').'h',
                '12m' => $d->translatedFormat('M Y'),
                default => $d->translatedFormat('d M'),
            };
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function recentActivities(Hotel $hotel, int $limit = 8)
    {
        return ActivityLog::where('hotel_id', $hotel->id)
            ->with('user:id,name')->latest('created_at')->latest('id')->limit($limit)->get();
    }

    /** Compteur de la cloche : réservations en attente de confirmation. */
    public function pendingCount(Hotel $hotel): int
    {
        return Reservation::where('hotel_id', $hotel->id)->where('status', Reservation::PENDING)->count();
    }

    private function revenueBetween(Hotel $hotel, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) Payment::where('hotel_id', $hotel->id)->whereBetween('paid_at', [$from, $to])->sum('amount')
            + (int) Sale::where('hotel_id', $hotel->id)->whereBetween('sold_at', [$from, $to])->sum('amount');
    }

    private function pct(int $part, int $total): int
    {
        return $total > 0 ? (int) round($part / $total * 100) : 0;
    }

    /** Expression SQL de regroupement, compatible MySQL (prod) et SQLite (tests). */
    private function bucketExpression(string $unit, string $column = 'paid_at'): string
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';

        return match ($unit) {
            'hour' => $sqlite ? "strftime('%H', {$column})" : "DATE_FORMAT({$column}, '%H')",
            'month' => $sqlite ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')",
            default => $sqlite ? "strftime('%Y-%m-%d', {$column})" : "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }
}
