<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\VehicleType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public static function compatibility(): array
    {
        return [
            'sepeda -> garasi sepeda' => [VehicleType::Bicycle, GarageType::Bicycle, true],
            'sepeda -> garasi mobil' => [VehicleType::Bicycle, GarageType::Car, false],
            'sepeda -> garasi listrik' => [VehicleType::Bicycle, GarageType::ElectricCar, false],
            'motor -> garasi sepeda' => [VehicleType::Motorcycle, GarageType::Bicycle, true],
            'motor -> garasi mobil' => [VehicleType::Motorcycle, GarageType::Car, false],
            'motor -> garasi listrik' => [VehicleType::Motorcycle, GarageType::ElectricCar, false],
            'mobil -> garasi sepeda' => [VehicleType::Car, GarageType::Bicycle, false],
            'mobil -> garasi mobil' => [VehicleType::Car, GarageType::Car, true],
            'mobil -> garasi listrik' => [VehicleType::Car, GarageType::ElectricCar, false],
            'mobil listrik -> garasi sepeda' => [VehicleType::ElectricCar, GarageType::Bicycle, false],
            'mobil listrik -> garasi mobil' => [VehicleType::ElectricCar, GarageType::Car, true],
            'mobil listrik -> garasi listrik' => [VehicleType::ElectricCar, GarageType::ElectricCar, true],
        ];
    }

    #[DataProvider('compatibility')]
    public function test_vehicle_garage_compatibility(VehicleType $vehicle, GarageType $garage, bool $expected): void
    {
        $this->assertSame($expected, $vehicle->canUseGarage($garage));
        $this->assertSame($expected, in_array($garage, $vehicle->allowedGarageTypes(), true));
    }

    public function test_every_vehicle_type_has_at_least_one_garage_type(): void
    {
        foreach (VehicleType::cases() as $type) {
            $this->assertNotEmpty($type->allowedGarageTypes(), $type->value);
        }
    }

    public function test_plate_and_battery_requirements(): void
    {
        $this->assertFalse(VehicleType::Bicycle->requiresPlateNumber());
        foreach ([VehicleType::Motorcycle, VehicleType::Car, VehicleType::ElectricCar] as $type) {
            $this->assertTrue($type->requiresPlateNumber(), $type->value);
        }

        $this->assertTrue(VehicleType::ElectricCar->requiresBatteryCapacity());
        foreach ([VehicleType::Bicycle, VehicleType::Motorcycle, VehicleType::Car] as $type) {
            $this->assertFalse($type->requiresBatteryCapacity(), $type->value);
        }
    }

    public function test_garage_code_prefix_and_electricity_price(): void
    {
        $this->assertSame('S', GarageType::Bicycle->codePrefix());
        $this->assertSame('C', GarageType::Car->codePrefix());
        $this->assertSame('E', GarageType::ElectricCar->codePrefix());

        $this->assertFalse(GarageType::Bicycle->hasElectricityPrice());
        $this->assertFalse(GarageType::Car->hasElectricityPrice());
        $this->assertTrue(GarageType::ElectricCar->hasElectricityPrice());
    }

    public function test_labels_are_present_and_unique(): void
    {
        foreach ([GarageType::cases(), VehicleType::cases(), PaymentStatus::cases()] as $cases) {
            $labels = array_map(fn ($c) => $c->label(), $cases);
            $this->assertCount(count($cases), array_unique($labels));
            $this->assertNotContains('', $labels);
        }
    }

    public function test_stored_values_are_stable(): void
    {
        // Nilai ini tersimpan di database; mengubahnya merusak data lama.
        $this->assertSame(['bicycle', 'car', 'electric_car'], array_map(fn ($c) => $c->value, GarageType::cases()));
        $this->assertSame(['bicycle', 'motorcycle', 'car', 'electric_car'], array_map(fn ($c) => $c->value, VehicleType::cases()));
        $this->assertSame(['pending', 'approved', 'rejected'], array_map(fn ($c) => $c->value, PaymentStatus::cases()));
    }
}
