<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\Rental;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan sewa. Hanya sewa dengan pembayaran TERVERIFIKASI yang dihitung. Semua nilai
 * sewa (termasuk perpanjangan) dihitung pada tanggal MULAI sewa. Biaya sewa dan biaya
 * listrik dipisahkan; 'revenue' adalah totalnya. Pengelompokan dilakukan di PHP supaya tidak bergantung pada
 * fungsi tanggal khusus MySQL.
 */
class RentalReportService
{
    public const VIEWS = ['monthly' => 'Per Bulan', 'daily' => 'Per Hari', 'type' => 'Per Tipe Garasi', 'penyewa' => 'Per Penyewa'];

    /**
     * @return array{rows: Collection<int, array{label:string,count:int,rental:float,electricity:float,revenue:float}>, count:int, rental:float, electricity:float, revenue:float}
     */
    public function build(string $view, int $year, ?int $month): array
    {
        $month = $view === 'daily' ? ($month ?? (int) now()->month) : $month;

        [$from, $to] = $this->range($view, $year, $month);

        // whereDate (bukan whereBetween) supaya hari terakhir periode ikut terhitung di semua database.
        $rentals = Rental::query()
            ->approved()
            ->with(['garage', 'penyewa'])
            ->whereDate('start_date', '>=', $from)
            ->whereDate('start_date', '<=', $to)
            ->get();

        $rows = match ($view) {
            'monthly' => $this->monthly($rentals),
            'daily' => $this->daily($rentals, $year, $month),
            'type' => $this->byType($rentals),
            default => $this->byPenyewa($rentals),
        };

        return [
            'rows' => $rows,
            'count' => $rows->sum('count'),
            'rental' => $rows->sum('rental'),
            'electricity' => $rows->sum('electricity'),
            'revenue' => $rows->sum('revenue'),
        ];
    }

    private function range(string $view, int $year, ?int $month): array
    {
        if ($month && $view !== 'monthly') {
            $start = Carbon::create($year, $month, 1)->startOfDay();

            return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
        }

        return ["{$year}-01-01", "{$year}-12-31"];
    }

    private function row(string $label, Collection $items): array
    {
        return [
            'label' => $label,
            'count' => $items->count(),
            'rental' => (float) $items->sum('rental_cost'),
            'electricity' => (float) $items->sum('electricity_cost'),
            'revenue' => (float) $items->sum('total_price'),
        ];
    }

    private function monthly(Collection $rentals): Collection
    {
        $by = $rentals->groupBy(fn ($r) => (int) $r->start_date->month);

        return collect(range(1, 12))->map(fn ($m) => $this->row(
            Carbon::create(2000, $m, 1)->translatedFormat('M'),
            $by->get($m, collect()),
        ));
    }

    private function daily(Collection $rentals, int $year, int $month): Collection
    {
        $by = $rentals->groupBy(fn ($r) => (int) $r->start_date->day);
        $days = Carbon::create($year, $month, 1)->daysInMonth;

        return collect(range(1, $days))->map(fn ($d) => $this->row((string) $d, $by->get($d, collect())));
    }

    private function byType(Collection $rentals): Collection
    {
        $by = $rentals->groupBy(fn ($r) => $r->garage->type->value);

        return collect(GarageType::cases())->map(fn ($t) => $this->row($t->label(), $by->get($t->value, collect())));
    }

    private function byPenyewa(Collection $rentals): Collection
    {
        return $rentals->groupBy('user_id')
            ->map(fn ($items) => $this->row($items->first()->penyewa->name, $items))
            ->sortByDesc('revenue')
            ->values();
    }
}
