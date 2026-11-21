<?php

namespace Tests\Unit\Services;

use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\VehicleType;
use App\Services\RentalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class RentalReportServiceTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private RentalReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday(); // Oktober 2026
        $this->service = app(RentalReportService::class);
    }

    public function test_monthly_has_twelve_rows_and_groups_by_start_month(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $this->rental($garage, $vehicle, '2026-03-05', '2026-03-06');            // Mar: 200.000
        $this->rental($garage, $vehicle, '2026-03-20', '2026-03-20');            // Mar: 100.000
        $this->rental($garage, $vehicle, '2026-05-30', '2026-06-02');            // dihitung di Mei (tanggal mulai), 400.000

        $r = $this->service->build('monthly', 2026, null);

        $this->assertCount(12, $r['rows']);
        $this->assertSame([2, 300000.0], [$r['rows'][2]['count'], $r['rows'][2]['revenue']]);
        $this->assertSame([1, 400000.0], [$r['rows'][4]['count'], $r['rows'][4]['revenue']]);
        $this->assertSame(0, $r['rows'][5]['count']); // Juni kosong
        $this->assertSame(3, $r['count']);
        $this->assertSame(700000.0, $r['revenue']);
    }

    public function test_only_approved_payments_are_counted(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $this->rental($garage, $vehicle, '2026-03-01', '2026-03-01', PaymentStatus::Approved);
        $this->rental($garage, $vehicle, '2026-03-05', '2026-03-05', PaymentStatus::Pending);
        $this->rental($garage, $vehicle, '2026-03-09', '2026-03-09', PaymentStatus::Rejected);

        $r = $this->service->build('monthly', 2026, null);

        $this->assertSame(1, $r['count']);
        $this->assertSame(100000.0, $r['revenue']);
    }

    public function test_year_boundary_uses_start_date(): void
    {
        $garage = $this->garage();
        $this->rental($garage, $this->vehicle(), '2025-12-31', '2026-01-02'); // mulai 2025

        $this->assertSame(1, $this->service->build('monthly', 2025, null)['count']);
        $this->assertSame(0, $this->service->build('monthly', 2026, null)['count']);
    }

    public function test_electricity_is_reported_separately(): void
    {
        $garage = $this->garage();
        $this->rental($garage, $this->vehicle(), '2026-03-05', '2026-03-06', attrs: ['electricity_cost' => 50000, 'total_price' => 250000]);

        $r = $this->service->build('monthly', 2026, null);

        $this->assertSame(200000.0, $r['rental']);
        $this->assertSame(50000.0, $r['electricity']);
        $this->assertSame(250000.0, $r['revenue']);
        $this->assertSame(250000.0, $r['rows'][2]['revenue']);
        $this->assertSame(50000.0, $r['rows'][2]['electricity']);
    }

    public function test_daily_view_rows_match_days_in_month(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $this->rental($garage, $vehicle, '2026-10-03', '2026-10-03');
        $this->rental($garage, $vehicle, '2026-09-30', '2026-09-30'); // bulan lain

        $oct = $this->service->build('daily', 2026, 10);
        $this->assertCount(31, $oct['rows']);
        $this->assertSame(1, $oct['rows'][2]['count']);
        $this->assertSame(1, $oct['count']);

        $this->assertCount(28, $this->service->build('daily', 2027, 2)['rows']);
        $this->assertCount(29, $this->service->build('daily', 2028, 2)['rows']);
    }

    public function test_daily_view_defaults_to_current_month(): void
    {
        $this->rental($this->garage(), $this->vehicle(), '2026-10-03', '2026-10-03');

        $r = $this->service->build('daily', 2026, null);

        $this->assertCount(31, $r['rows']);
        $this->assertSame(1, $r['count']);
    }

    public function test_by_type_always_lists_all_three_types(): void
    {
        $this->rental($this->garage(GarageType::Car), $this->vehicle(), '2026-03-01', '2026-03-02');

        $r = $this->service->build('type', 2026, null);

        $this->assertSame(['Garasi Sepeda', 'Garasi Mobil', 'Garasi Mobil Listrik'], $r['rows']->pluck('label')->all());
        $this->assertSame([0, 1, 0], $r['rows']->pluck('count')->all());
    }

    public function test_by_penyewa_sorted_by_revenue_desc_and_skips_idle_users(): void
    {
        $garage = $this->garage();
        $small = $this->penyewaUser(['name' => 'Kecil']);
        $big = $this->penyewaUser(['name' => 'Besar']);
        $this->penyewaUser(['name' => 'Tidak Pernah Sewa']);
        $this->rental($garage, $this->vehicle($small), '2026-03-01', '2026-03-01');
        $this->rental($garage, $this->vehicle($big), '2026-03-05', '2026-03-09');
        $this->rental($garage, $this->vehicle($big), '2026-04-05', '2026-04-05');

        $r = $this->service->build('penyewa', 2026, null);

        $this->assertSame(['Besar', 'Kecil'], $r['rows']->pluck('label')->all());
        $this->assertSame([2, 1], $r['rows']->pluck('count')->all());
        $this->assertSame([600000.0, 100000.0], $r['rows']->pluck('revenue')->all());
    }

    public function test_month_filter_applies_to_type_and_penyewa_views_but_not_monthly(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $this->rental($garage, $vehicle, '2026-03-01', '2026-03-01');
        $this->rental($garage, $vehicle, '2026-04-01', '2026-04-01');

        $this->assertSame(1, $this->service->build('type', 2026, 3)['count']);
        $this->assertSame(1, $this->service->build('penyewa', 2026, 4)['count']);
        $this->assertSame(2, $this->service->build('type', 2026, null)['count']);
        $this->assertSame(2, $this->service->build('monthly', 2026, 3)['count']); // bulan diabaikan
    }

    public function test_empty_report(): void
    {
        $r = $this->service->build('penyewa', 2026, null);

        $this->assertCount(0, $r['rows']);
        $this->assertSame(0, $r['count']);
        $this->assertEquals(0, $r['revenue']);
    }

    public function test_view_list_matches_supported_views(): void
    {
        $this->assertSame(['monthly', 'daily', 'type', 'penyewa'], array_keys(RentalReportService::VIEWS));
        $this->assertNotEmpty(VehicleType::cases());
    }
}
