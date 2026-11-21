<?php

namespace Tests\Unit;

use App\Enums\RentalType;
use App\Services\RentalPeriodCalculator as Calc;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RentalPeriodCalculatorTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'Feb 2024 kabisat, 28 hari -> harian' => ['2024-02-27', '2024-03-25', RentalType::Daily],
            'Feb 2024 kabisat, tepat 29 hari -> bulanan' => ['2024-02-27', '2024-03-26', RentalType::Monthly],
            'Feb 2025 non-kabisat, 28 hari -> bulanan' => ['2025-02-10', '2025-03-09', RentalType::Monthly],
            'Feb 2025 non-kabisat, 27 hari -> harian' => ['2025-02-10', '2025-03-08', RentalType::Daily],
            'Okt, 30 hari -> harian' => ['2025-10-01', '2025-10-30', RentalType::Daily],
            'Okt, tepat 31 hari -> bulanan' => ['2025-10-01', '2025-10-31', RentalType::Monthly],
            'April, tepat 30 hari -> bulanan' => ['2025-04-01', '2025-04-30', RentalType::Monthly],
            'sehari -> harian' => ['2025-10-01', '2025-10-01', RentalType::Daily],
        ];
    }

    #[DataProvider('cases')]
    public function test_classify(string $start, string $end, RentalType $expected): void
    {
        $this->assertSame($expected, Calc::classify(Carbon::parse($start), Carbon::parse($end)));
    }

    public function test_single_rental_is_limited_to_one_month(): void
    {
        // Feb 2024 kabisat: maksimal 29 hari
        $this->assertTrue(Calc::isWithinMaxDuration(Carbon::parse('2024-02-27'), Carbon::parse('2024-03-26')));
        $this->assertFalse(Calc::isWithinMaxDuration(Carbon::parse('2024-02-27'), Carbon::parse('2024-03-27')));
        // Oktober: maksimal 31 hari
        $this->assertTrue(Calc::isWithinMaxDuration(Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31')));
        $this->assertFalse(Calc::isWithinMaxDuration(Carbon::parse('2025-10-01'), Carbon::parse('2025-11-01')));
        // tanggal selesai sebelum mulai
        $this->assertFalse(Calc::isWithinMaxDuration(Carbon::parse('2025-10-05'), Carbon::parse('2025-10-01')));
    }

    public function test_duration_is_inclusive(): void
    {
        $this->assertSame(1, Calc::durationInDays(Carbon::parse('2025-10-01'), Carbon::parse('2025-10-01')));
        $this->assertSame(29, Calc::durationInDays(Carbon::parse('2024-02-27'), Carbon::parse('2024-03-26')));
    }

    public function test_end_date_and_earliest_monthly_end_date(): void
    {
        $this->assertSame('2025-10-03', Calc::endDateForDays(Carbon::parse('2025-10-01'), 3)->toDateString());
        $this->assertSame('2024-03-26', Calc::earliestMonthlyEndDate(Carbon::parse('2024-02-27'))->toDateString());
        $this->assertSame('2025-10-31', Calc::earliestMonthlyEndDate(Carbon::parse('2025-10-01'))->toDateString());
    }

    public function test_start_date_cannot_be_backdated(): void
    {
        $today = Carbon::parse('2025-10-10 15:00');

        $this->assertTrue(Calc::isValidStartDate(Carbon::parse('2025-10-10'), $today));
        $this->assertTrue(Calc::isValidStartDate(Carbon::parse('2025-10-11'), $today));
        $this->assertTrue(Calc::isValidStartDate(Carbon::parse('2025-10-17'), $today));
        $this->assertFalse(Calc::isValidStartDate(Carbon::parse('2025-10-09'), $today));
    }

    public function test_breakdown_for_new_and_extended_rentals(): void
    {
        // sewa baru: < 1 bulan harian, tepat 1 bulan satu blok bulanan
        $this->assertSame(['monthly' => 0, 'daily' => 10], Calc::breakdown(Carbon::parse('2025-10-01'), Carbon::parse('2025-10-10')));
        $this->assertSame(['monthly' => 1, 'daily' => 0], Calc::breakdown(Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31')));
        // perpanjangan: bulan pertama bulanan, sisanya harian (tidak berulang bulanan)
        $this->assertSame(['monthly' => 1, 'daily' => 5], Calc::breakdown(Carbon::parse('2025-10-01'), Carbon::parse('2025-11-05')));
        $this->assertSame(['monthly' => 1, 'daily' => 61], Calc::breakdown(Carbon::parse('2025-10-01'), Carbon::parse('2025-12-31')));
        // Feb 2024 kabisat: 27 Feb -> 31 Mar = 29 + 5 hari
        $this->assertSame(['monthly' => 1, 'daily' => 5], Calc::breakdown(Carbon::parse('2024-02-27'), Carbon::parse('2024-03-31')));
    }

    public function test_rental_cost(): void
    {
        // 3 hari harian
        $this->assertSame('300000.00', Calc::rentalCost(Carbon::parse('2025-10-01'), Carbon::parse('2025-10-03'), '100000'));
        // tepat 1 bulan = 25 x harian
        $this->assertSame('2500000.00', Calc::rentalCost(Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'), '100000'));
        // 1 bulan + 5 hari
        $this->assertSame('3000000.00', Calc::rentalCost(Carbon::parse('2025-10-01'), Carbon::parse('2025-11-05'), '100000'));
        // 92 hari: 1 bulan (2.500.000) + 61 hari harian (6.100.000)
        $this->assertSame('8600000.00', Calc::rentalCost(Carbon::parse('2025-10-01'), Carbon::parse('2025-12-31'), '100000'));
    }

    public static function monthThresholds(): array
    {
        return [
            'Jan 2025' => ['2025-01-15', 31], 'Feb 2025 (28)' => ['2025-02-10', 28], 'Feb 2024 kabisat (29)' => ['2024-02-10', 29],
            'Mar' => ['2025-03-01', 31], 'Apr' => ['2025-04-01', 30], 'Mei' => ['2025-05-01', 31], 'Jun' => ['2025-06-30', 30],
            'Jul' => ['2025-07-01', 31], 'Agu' => ['2025-08-01', 31], 'Sep' => ['2025-09-01', 30], 'Okt' => ['2025-10-31', 31],
            'Nov' => ['2025-11-01', 30], 'Des' => ['2025-12-01', 31],
        ];
    }

    #[DataProvider('monthThresholds')]
    public function test_monthly_threshold_is_days_in_start_month(string $start, int $expected): void
    {
        $this->assertSame($expected, Calc::monthlyThreshold(Carbon::parse($start)));
    }

    public function test_threshold_follows_start_month_not_end_month(): void
    {
        // Mulai 25 Feb 2025 (28 hari): 28 hari sampai 24 Mar sudah bulanan, walau Maret 31 hari.
        $this->assertSame(RentalType::Monthly, Calc::classify(Carbon::parse('2025-02-25'), Carbon::parse('2025-03-24')));
        // Mulai 25 Mar 2025 (31 hari): rentang yang sama panjang tetap harian.
        $this->assertSame(RentalType::Daily, Calc::classify(Carbon::parse('2025-03-25'), Carbon::parse('2025-04-21')));
    }

    public function test_start_at_month_end_crosses_into_next_month(): void
    {
        // 31 Jan + 31 hari inklusif = 2 Maret (Feb 2025 hanya 28 hari)
        $this->assertSame('2025-03-02', Calc::earliestMonthlyEndDate(Carbon::parse('2025-01-31'))->toDateString());
        // tahun kabisat: 31 Jan 2024 -> 1 Maret
        $this->assertSame('2024-03-01', Calc::earliestMonthlyEndDate(Carbon::parse('2024-01-31'))->toDateString());
    }

    public function test_year_boundary(): void
    {
        $this->assertSame(RentalType::Monthly, Calc::classify(Carbon::parse('2025-12-20'), Carbon::parse('2026-01-19'))); // 31 hari
        $this->assertSame(RentalType::Daily, Calc::classify(Carbon::parse('2025-12-20'), Carbon::parse('2026-01-18'))); // 30 hari
        $this->assertSame('2026-01-19', Calc::earliestMonthlyEndDate(Carbon::parse('2025-12-20'))->toDateString());
    }

    public function test_time_of_day_is_ignored(): void
    {
        $this->assertSame(2, Calc::durationInDays(Carbon::parse('2025-10-01 23:59:59'), Carbon::parse('2025-10-02 00:00:01')));
        $this->assertSame(1, Calc::durationInDays(Carbon::parse('2025-10-01 00:00:00'), Carbon::parse('2025-10-01 23:59:59')));
    }

    public function test_breakdown_edges(): void
    {
        $oct1 = Carbon::parse('2025-10-01');
        $this->assertSame(['monthly' => 1, 'daily' => 1], Calc::breakdown($oct1, Carbon::parse('2025-11-01'))); // 32 hari
        $this->assertSame(['monthly' => 0, 'daily' => 30], Calc::breakdown($oct1, Carbon::parse('2025-10-30')));
        // Feb 2025 (28 hari): 28 hari = bulanan, 27 hari = harian
        $feb = Carbon::parse('2025-02-01');
        $this->assertSame(['monthly' => 1, 'daily' => 0], Calc::breakdown($feb, Carbon::parse('2025-02-28')));
        $this->assertSame(['monthly' => 0, 'daily' => 27], Calc::breakdown($feb, Carbon::parse('2025-02-27')));
        // perpanjangan panjang: bulan pertama bulanan, sisanya harian
        $this->assertSame(['monthly' => 1, 'daily' => 305], Calc::breakdown($oct1, Carbon::parse('2026-09-01')));
    }

    public function test_end_before_start_yields_nothing_billable(): void
    {
        $start = Carbon::parse('2025-10-05');
        $end = Carbon::parse('2025-10-01');

        $this->assertSame(['monthly' => 0, 'daily' => 0], Calc::breakdown($start, $end));
        $this->assertSame('0.00', Calc::rentalCost($start, $end, '100000'));
        $this->assertFalse(Calc::isWithinMaxDuration($start, $end));
    }

    public function test_cost_keeps_decimal_precision(): void
    {
        $oct1 = Carbon::parse('2025-10-01');

        $this->assertSame('37501.50', Calc::rentalCost($oct1, Carbon::parse('2025-10-03'), '12500.50'));
        $this->assertSame('312512.50', Calc::rentalCost($oct1, Carbon::parse('2025-10-31'), '12500.50'));
        $this->assertSame('0.00', Calc::rentalCost($oct1, Carbon::parse('2025-10-03'), '0'));
    }

    public function test_extension_cost_can_drop_when_crossing_into_monthly(): void
    {
        // Aturan harga yang diketahui: 30 hari harian (3.000.000) lebih mahal dari 31 hari bulanan (2.500.000).
        $oct1 = Carbon::parse('2025-10-01');
        $daily30 = Calc::rentalCost($oct1, Calc::endDateForDays($oct1, 30), '100000');
        $monthly31 = Calc::rentalCost($oct1, Calc::endDateForDays($oct1, 31), '100000');

        $this->assertSame('3000000.00', $daily30);
        $this->assertSame('2500000.00', $monthly31);
        $this->assertSame('-500000.00', bcsub($monthly31, $daily30, 2));
    }

    public function test_start_date_validation_uses_calendar_day(): void
    {
        $now = Carbon::parse('2025-10-10 23:30');

        $this->assertTrue(Calc::isValidStartDate(Carbon::parse('2025-10-10 00:00'), $now));
        $this->assertFalse(Calc::isValidStartDate(Carbon::parse('2025-10-09 23:59'), $now));
    }
}
