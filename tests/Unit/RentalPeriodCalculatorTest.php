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
}
