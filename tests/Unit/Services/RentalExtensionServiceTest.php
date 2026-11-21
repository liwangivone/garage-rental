<?php

namespace Tests\Unit\Services;

use App\Enums\GarageStatus;
use App\Enums\RentalType;
use App\Models\GaragePrice;
use App\Services\RentalExtensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class RentalExtensionServiceTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private RentalExtensionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->freezeToday(); // 10 Okt 2026
        $this->service = app(RentalExtensionService::class);
    }

    private function d(string $date): Carbon
    {
        return Carbon::parse($date);
    }

    public function test_preview_within_daily_range(): void
    {
        $rental = $this->rental($this->garage(), $this->vehicle(), '2026-10-01', '2026-10-05'); // 5 hari = 500.000

        $p = $this->service->preview($rental, $this->d('2026-10-10')); // 10 hari

        $this->assertSame(['monthly' => 0, 'daily' => 10], $p['breakdown']);
        $this->assertSame(10, $p['duration_days']);
        $this->assertSame('1000000.00', $p['rental_cost']);
        $this->assertSame('500000.00', $p['previous_total']);
        $this->assertSame('1000000.00', $p['new_total']);
        $this->assertSame('500000.00', $p['additional_cost']);
    }

    public function test_preview_crossing_into_monthly(): void
    {
        $rental = $this->rental($this->garage(), $this->vehicle(), '2026-10-01', '2026-10-05');

        $p = $this->service->preview($rental, $this->d('2026-11-05')); // 36 hari = 1 bulan + 5 hari

        $this->assertSame(['monthly' => 1, 'daily' => 5], $p['breakdown']);
        $this->assertSame('3000000.00', $p['new_total']);
        $this->assertSame('2500000.00', $p['additional_cost']);
    }

    public function test_preview_can_be_negative_when_crossing_the_monthly_threshold(): void
    {
        // Keputusan bisnis: dibiarkan. 30 hari harian = 3.000.000 > 31 hari bulanan = 2.500.000.
        $rental = $this->rental($this->garage(), $this->vehicle(), '2026-10-01', '2026-10-30');

        $p = $this->service->preview($rental, $this->d('2026-10-31'));

        $this->assertSame('-500000.00', $p['additional_cost']);
    }

    public function test_preview_uses_price_snapshot_not_current_price(): void
    {
        $garage = $this->garage();
        $rental = $this->rental($garage, $this->vehicle(), '2026-10-01', '2026-10-05');
        GaragePrice::where('type', $garage->type)->update(['daily_price' => 500000]);

        $p = $this->service->preview($rental->fresh(), $this->d('2026-10-10'));

        $this->assertSame('1000000.00', $p['new_total']); // tetap 100.000/hari
    }

    public function test_preview_adds_extra_electricity(): void
    {
        $rental = $this->rental($this->garage(), $this->vehicle(), '2026-10-01', '2026-10-05', attrs: [
            'electricity_cost' => 100000, 'total_price' => 600000,
        ]);

        $p = $this->service->preview($rental, $this->d('2026-10-06'), '50000');

        $this->assertSame('150000.00', $p['electricity_cost']);
        $this->assertSame('50000.00', $p['extra_electricity']);
        $this->assertSame('600000.00', $p['rental_cost']); // 6 hari
        $this->assertSame('750000.00', $p['new_total']);
        $this->assertSame('150000.00', $p['additional_cost']); // 100.000 (1 hari) + 50.000 listrik
    }

    public function test_preview_without_extra_electricity_keeps_existing(): void
    {
        $rental = $this->rental($this->garage(), $this->vehicle(), '2026-10-01', '2026-10-05', attrs: [
            'electricity_cost' => 100000, 'total_price' => 600000,
        ]);

        $p = $this->service->preview($rental, $this->d('2026-10-06'));

        $this->assertSame('100000.00', $p['electricity_cost']);
        $this->assertSame('0.00', $p['extra_electricity']);
    }

    public function test_extend_updates_rental_and_records_history(): void
    {
        $admin = $this->adminUser();
        $rental = $this->rental($this->garage(), $this->vehicle(), '2026-10-08', '2026-10-12'); // 5 hari = 500.000

        $ext = $this->service->extend($rental, $this->d('2026-10-20'), UploadedFile::fake()->image('b.jpg'), $admin, '25000');

        $rental->refresh();
        $this->assertSame('2026-10-20', $rental->end_date->toDateString());
        $this->assertSame(13, $rental->duration_days);
        $this->assertSame(0, $rental->monthly_count);
        $this->assertSame(13, $rental->daily_count);
        $this->assertSame('1300000.00', $rental->rental_cost);
        $this->assertSame('25000.00', $rental->electricity_cost);
        $this->assertSame('1325000.00', $rental->total_price);

        $this->assertSame($rental->id, $ext->rental_id);
        $this->assertSame($admin->id, $ext->processed_by);
        $this->assertSame('2026-10-12', $ext->previous_end_date->toDateString());
        $this->assertSame('2026-10-20', $ext->new_end_date->toDateString());
        $this->assertSame('500000.00', $ext->previous_total_price);
        $this->assertSame('1325000.00', $ext->new_total_price);
        $this->assertSame('825000.00', $ext->additional_cost);
        $this->assertSame('25000.00', $ext->electricity_cost);
        Storage::disk('public')->assertExists($ext->payment_proof_path);
    }

    public function test_extend_into_monthly_changes_type(): void
    {
        $rental = $this->rental($this->garage(), $this->vehicle(), '2026-10-08', '2026-10-12');

        $ext = $this->service->extend($rental, $this->d('2026-11-30'), UploadedFile::fake()->image('b.jpg'), $this->adminUser());

        $rental->refresh();
        $this->assertSame(RentalType::Daily, $ext->previous_rental_type);
        $this->assertSame(RentalType::Monthly, $ext->new_rental_type);
        $this->assertSame(RentalType::Monthly, $rental->rental_type);
        $this->assertSame(1, $rental->monthly_count);
        $this->assertSame(54 - 31, $rental->daily_count); // 8 Okt - 30 Nov = 54 hari
    }

    public function test_extend_keeps_garage_status_in_sync(): void
    {
        $active = $this->rental($this->garage(\App\Enums\GarageType::Car, 1), $this->vehicle(), '2026-10-08', '2026-10-12');
        $upcoming = $this->rental($this->garage(\App\Enums\GarageType::Car, 2), $this->vehicle(), '2026-10-15', '2026-10-16');

        $this->service->extend($active, $this->d('2026-10-14'), UploadedFile::fake()->image('a.jpg'), $this->adminUser());
        $this->service->extend($upcoming, $this->d('2026-10-18'), UploadedFile::fake()->image('b.jpg'), $this->adminUser());

        $this->assertSame(GarageStatus::Used, $active->garage->fresh()->status);
        $this->assertSame(GarageStatus::Available, $upcoming->garage->fresh()->status); // belum mulai
    }
}
