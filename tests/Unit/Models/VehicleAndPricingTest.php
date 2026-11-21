<?php

namespace Tests\Unit\Models;

use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\VehicleType;
use App\Models\GarageBatch;
use App\Models\GaragePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class VehicleAndPricingTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    public function test_vehicle_rental_overlap_boundaries(): void
    {
        $vehicle = $this->vehicle();
        $this->rental($this->garage(), $vehicle, '2026-10-20', '2026-10-22');
        $d = fn (string $x) => \Carbon\Carbon::parse($x);

        $this->assertFalse($vehicle->hasRentalBetween($d('2026-10-18'), $d('2026-10-19')));
        $this->assertTrue($vehicle->hasRentalBetween($d('2026-10-19'), $d('2026-10-20')));
        $this->assertTrue($vehicle->hasRentalBetween($d('2026-10-22'), $d('2026-10-25')));
        $this->assertFalse($vehicle->hasRentalBetween($d('2026-10-23'), $d('2026-10-25'))); // sewa kedua setelah yang pertama selesai
    }

    public function test_vehicle_overlap_ignores_rejected_and_given_rental(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $rejected = $this->rental($garage, $vehicle, '2026-10-20', '2026-10-22', PaymentStatus::Rejected);
        $d = fn (string $x) => \Carbon\Carbon::parse($x);

        $this->assertFalse($vehicle->hasRentalBetween($d('2026-10-20'), $d('2026-10-22')));

        $rejected->update(['payment_status' => PaymentStatus::Pending]);
        $this->assertTrue($vehicle->hasRentalBetween($d('2026-10-20'), $d('2026-10-22')));
        $this->assertFalse($vehicle->hasRentalBetween($d('2026-10-20'), $d('2026-10-22'), $rejected->id));
    }

    public function test_vehicle_overlap_is_per_vehicle(): void
    {
        $garage = $this->garage();
        $a = $this->vehicle();
        $b = $this->vehicle();
        $this->rental($garage, $a, '2026-10-20', '2026-10-22');

        $this->assertFalse($b->hasRentalBetween(\Carbon\Carbon::parse('2026-10-20'), \Carbon\Carbon::parse('2026-10-22')));
    }

    public function test_vehicle_can_use_garage_type_and_casts(): void
    {
        $ev = $this->vehicle(null, VehicleType::ElectricCar, ['battery_capacity_kwh' => 57.5])->fresh();

        $this->assertSame(VehicleType::ElectricCar, $ev->type);
        $this->assertSame('57.50', $ev->battery_capacity_kwh);
        $this->assertTrue($ev->canUseGarageType(GarageType::Car));
        $this->assertTrue($ev->canUseGarageType(GarageType::ElectricCar));
        $this->assertFalse($ev->canUseGarageType(GarageType::Bicycle));
    }

    public function test_plate_number_may_repeat_across_vehicles(): void
    {
        $this->vehicle(null, VehicleType::Car, ['plate_number' => 'DD 1 A']);
        $this->vehicle(null, VehicleType::Car, ['plate_number' => 'DD 1 A']);

        $this->assertSame(2, \App\Models\Vehicle::where('plate_number', 'DD 1 A')->count());
    }

    public function test_monthly_price_is_25_times_daily(): void
    {
        $batch = new GarageBatch(['daily_price' => '12500.50']);
        $price = new GaragePrice(['daily_price' => '12500.50']);

        $this->assertSame('312512.50', $batch->monthly_price);
        $this->assertSame('312512.50', $price->monthly_price);
        $this->assertSame(25, GarageBatch::MONTHLY_PRICE_MULTIPLIER);
    }

    public function test_garage_price_lookup(): void
    {
        $this->assertNull(GaragePrice::forType(GarageType::Car));

        $this->garage(GarageType::Car, 1, '75000');

        $this->assertSame('75000.00', GaragePrice::forType(GarageType::Car)->daily_price);
        $this->assertNull(GaragePrice::forType(GarageType::Bicycle));
    }
}
