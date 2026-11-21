<?php

namespace App\Services;

use App\Enums\RentalType;
use App\Models\GarageBatch;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Aturan sewa harian vs bulanan.
 *
 * Batas bulanan = jumlah hari pada BULAN TANGGAL MULAI sewa
 * (Feb 2024 = 29, Feb 2025 = 28, Okt = 31, dst).
 *
 * Penagihan (breakdown) untuk rentang start..end:
 *   - durasi < batas  -> semuanya harian (durasi x harga harian)
 *   - durasi >= batas -> SATU blok bulanan (25 x harga harian) untuk bulan pertama,
 *                        sisa harinya harian (berapa pun panjangnya)
 *
 * Sewa BARU maksimal satu bulan per transaksi (lihat isWithinMaxDuration()).
 * PERPANJANGAN oleh admin tidak dibatasi, dan dihitung dengan aturan yang sama
 * dari tanggal mulai asli: bulan pertama bulanan, sisanya harian.
 *
 * PENDING: durasi saat ini dihitung inklusif (tanggal mulai & selesai sama-sama
 * dihitung). Jika aturan ini diubah, cukup ubah durationInDays() dan endDateForDays().
 */
class RentalPeriodCalculator
{
    /** Jumlah hari dalam bulan tanggal mulai = panjang satu blok bulanan. */
    public static function monthlyThreshold(CarbonInterface $start): int
    {
        return $start->daysInMonth;
    }

    /** Durasi inklusif dalam hari. Contoh: 27 Feb - 27 Feb = 1 hari. */
    public static function durationInDays(CarbonInterface $start, CarbonInterface $end): int
    {
        $from = Carbon::instance($start)->startOfDay();
        $to = Carbon::instance($end)->startOfDay();

        return (int) $from->diffInDays($to) + 1;
    }

    /** Tanggal mulai boleh dipilih bebas, tetapi tidak boleh sebelum hari ini. */
    public static function isValidStartDate(CarbonInterface $start, ?CarbonInterface $today = null): bool
    {
        $today = Carbon::instance($today ?? now())->startOfDay();

        return Carbon::instance($start)->startOfDay()->gte($today);
    }

    /** Durasi maksimal SEWA BARU = satu bulan (jumlah hari bulan tanggal mulai). */
    public static function maxDurationDays(CarbonInterface $start): int
    {
        return self::monthlyThreshold($start);
    }

    public static function isWithinMaxDuration(CarbonInterface $start, CarbonInterface $end): bool
    {
        $days = self::durationInDays($start, $end);

        return $days >= 1 && $days <= self::maxDurationDays($start);
    }

    /**
     * Pecah rentang menjadi blok bulanan dan sisa hari harian.
     *
     * @return array{monthly: int, daily: int}
     */
    public static function breakdown(CarbonInterface $start, CarbonInterface $end): array
    {
        $days = self::durationInDays($start, $end);
        $threshold = self::monthlyThreshold($start);

        if ($days >= $threshold) {
            return ['monthly' => 1, 'daily' => $days - $threshold];
        }

        return ['monthly' => 0, 'daily' => max($days, 0)];
    }

    /** Daily bila tidak ada blok bulanan sama sekali, selain itu Monthly. */
    public static function classify(CarbonInterface $start, CarbonInterface $end): RentalType
    {
        return self::breakdown($start, $end)['monthly'] > 0 ? RentalType::Monthly : RentalType::Daily;
    }

    /** Tanggal selesai untuk sewa sebanyak $days hari (inklusif). */
    public static function endDateForDays(CarbonInterface $start, int $days): Carbon
    {
        return Carbon::instance($start)->startOfDay()->addDays($days - 1);
    }

    /** Tanggal selesai paling awal agar tercatat sebagai sewa bulanan. */
    public static function earliestMonthlyEndDate(CarbonInterface $start): Carbon
    {
        return self::endDateForDays($start, self::monthlyThreshold($start));
    }

    /** Biaya sewa (tanpa listrik) untuk rentang start..end. */
    public static function rentalCost(CarbonInterface $start, CarbonInterface $end, string $dailyPrice): string
    {
        $parts = self::breakdown($start, $end);
        $monthlyPrice = bcmul($dailyPrice, (string) GarageBatch::MONTHLY_PRICE_MULTIPLIER, 2);

        return bcadd(
            bcmul($monthlyPrice, (string) $parts['monthly'], 2),
            bcmul($dailyPrice, (string) $parts['daily'], 2),
            2,
        );
    }
}
