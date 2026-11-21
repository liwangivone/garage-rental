<?php

namespace Tests\Unit\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\GarageBatch;
use App\Models\GaragePrice;
use App\Services\GarageBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class GarageBatchServiceTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private GarageBatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GarageBatchService::class);
    }

    private function codes(GarageType $type): array
    {
        return Garage::where('type', $type)->orderBy('sequence')->pluck('code')->all();
    }

    public function test_creates_sequential_codes_all_available(): void
    {
        $batch = $this->service->create($this->adminUser(), GarageType::Car, 3, '100000', null);

        $this->assertSame(['C001', 'C002', 'C003'], $this->codes(GarageType::Car));
        $this->assertSame(3, $batch->garages()->count());
        $this->assertSame(3, $batch->quantity);
        $this->assertSame($this->adminUser()->id, $batch->created_by);
        $this->assertSame(0, Garage::where('status', '!=', GarageStatus::Available)->count());
    }

    public function test_second_batch_continues_numbering(): void
    {
        $this->service->create($this->adminUser(), GarageType::Car, 2, '100000', null);
        $this->service->create($this->adminUser(), GarageType::Car, 2, '100000', null);

        $this->assertSame(['C001', 'C002', 'C003', 'C004'], $this->codes(GarageType::Car));
    }

    public function test_numbering_is_independent_per_type(): void
    {
        $this->service->create($this->adminUser(), GarageType::Car, 2, '100000', null);
        $this->service->create($this->adminUser(), GarageType::Bicycle, 1, '10000', null);
        $this->service->create($this->adminUser(), GarageType::ElectricCar, 1, '120000', '3000');

        $this->assertSame(['S001'], $this->codes(GarageType::Bicycle));
        $this->assertSame(['C001', 'C002'], $this->codes(GarageType::Car));
        $this->assertSame(['E001'], $this->codes(GarageType::ElectricCar));
    }

    public function test_type_price_is_created_then_updated_not_duplicated(): void
    {
        $this->service->create($this->adminUser(), GarageType::Car, 1, '100000', null);
        $this->assertSame('100000.00', GaragePrice::forType(GarageType::Car)->daily_price);

        $this->service->create($this->adminUser(), GarageType::Car, 1, '150000', null);

        $this->assertSame(1, GaragePrice::where('type', GarageType::Car)->count());
        $this->assertSame('150000.00', GaragePrice::forType(GarageType::Car)->daily_price);
        $this->assertSame(2, GarageBatch::where('type', GarageType::Car)->count()); // riwayat batch tetap tersimpan
    }

    public function test_electricity_price_only_kept_for_electric_garages(): void
    {
        $this->service->create($this->adminUser(), GarageType::Car, 1, '100000', '9999');
        $this->service->create($this->adminUser(), GarageType::ElectricCar, 1, '120000', '3500');

        $this->assertNull(GaragePrice::forType(GarageType::Car)->electricity_price_per_kwh);
        $this->assertNull(GarageBatch::where('type', GarageType::Car)->first()->electricity_price_per_kwh);
        $this->assertSame('3500.00', GaragePrice::forType(GarageType::ElectricCar)->electricity_price_per_kwh);
    }

    public function test_exactly_999_garages_is_allowed(): void
    {
        $this->service->create($this->adminUser(), GarageType::Bicycle, 999, '10000', null);

        $this->assertSame(999, Garage::where('type', GarageType::Bicycle)->count());
        $this->assertSame('S999', Garage::where('type', GarageType::Bicycle)->max('code'));
    }

    public function test_exceeding_999_is_rejected_and_nothing_changes(): void
    {
        $this->service->create($this->adminUser(), GarageType::Bicycle, 999, '10000', null);
        $batches = GarageBatch::count();

        try {
            $this->service->create($this->adminUser(), GarageType::Bicycle, 1, '99999', null);
            $this->fail('Seharusnya melempar ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $this->assertSame(999, Garage::where('type', GarageType::Bicycle)->count());
        $this->assertSame($batches, GarageBatch::count());
        $this->assertSame('10000.00', GaragePrice::forType(GarageType::Bicycle)->daily_price); // harga tidak ikut berubah
    }

    public function test_remaining_quota_is_reported(): void
    {
        $this->service->create($this->adminUser(), GarageType::Car, 990, '100000', null);

        try {
            $this->service->create($this->adminUser(), GarageType::Car, 10, '100000', null);
            $this->fail('Seharusnya melempar ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Sisa kuota', $e->errors()['quantity'][0]);
            $this->assertStringContainsString('9 garasi', $e->errors()['quantity'][0]);
        }

        $this->service->create($this->adminUser(), GarageType::Car, 9, '100000', null); // sisa kuota tepat
        $this->assertSame(999, Garage::where('type', GarageType::Car)->count());
    }

    public function test_full_type_does_not_block_other_types(): void
    {
        $this->service->create($this->adminUser(), GarageType::Bicycle, 999, '10000', null);

        $this->service->create($this->adminUser(), GarageType::Car, 1, '100000', null);
        $this->assertSame(['C001'], $this->codes(GarageType::Car));
    }
}
