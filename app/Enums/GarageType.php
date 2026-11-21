<?php

namespace App\Enums;

enum GarageType: string
{
    case Bicycle = 'bicycle';
    case Car = 'car';
    case ElectricCar = 'electric_car';

    /** Prefix kode garasi: S001, C001, E001. */
    public function codePrefix(): string
    {
        return match ($this) {
            self::Bicycle => 'S',
            self::Car => 'C',
            self::ElectricCar => 'E',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Bicycle => 'Garasi Sepeda',
            self::Car => 'Garasi Mobil',
            self::ElectricCar => 'Garasi Mobil Listrik',
        };
    }

    /** Hanya garasi mobil listrik yang punya harga isi listrik per kWh. */
    public function hasElectricityPrice(): bool
    {
        return $this === self::ElectricCar;
    }
}
