<?php

namespace App\Enums;

enum VehicleType: string
{
    case Bicycle = 'bicycle';
    case Motorcycle = 'motorcycle';
    case Car = 'car';
    case ElectricCar = 'electric_car';

    public function label(): string
    {
        return match ($this) {
            self::Bicycle => 'Sepeda',
            self::Motorcycle => 'Sepeda Motor',
            self::Car => 'Mobil',
            self::ElectricCar => 'Mobil Listrik',
        };
    }

    /**
     * Tipe garasi yang boleh dipakai kendaraan ini.
     * - Sepeda & sepeda motor: hanya garasi sepeda
     * - Mobil: hanya garasi mobil
     * - Mobil listrik: garasi mobil atau garasi mobil listrik
     *
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return match ($this) {
            self::Bicycle, self::Motorcycle => [GarageType::Bicycle],
            self::Car => [GarageType::Car],
            self::ElectricCar => [GarageType::Car, GarageType::ElectricCar],
        };
    }

    public function canUseGarage(GarageType $garageType): bool
    {
        return in_array($garageType, $this->allowedGarageTypes(), true);
    }

    /** Sepeda biasa tidak punya plat nomor. */
    public function requiresPlateNumber(): bool
    {
        return $this !== self::Bicycle;
    }

    /** Kapasitas baterai (kWh) hanya untuk mobil listrik. */
    public function requiresBatteryCapacity(): bool
    {
        return $this === self::ElectricCar;
    }
}
