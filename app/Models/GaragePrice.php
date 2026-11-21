<?php

namespace App\Models;

use App\Enums\GarageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Satu harga per tipe garasi (diperbarui admin saat membuat kelompok garasi). */
#[Fillable(['type', 'daily_price', 'electricity_price_per_kwh', 'updated_by'])]
class GaragePrice extends Model
{
    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'daily_price' => 'decimal:2',
            'electricity_price_per_kwh' => 'decimal:2',
        ];
    }

    public static function forType(GarageType $type): ?self
    {
        return static::where('type', $type)->first();
    }

    public function getMonthlyPriceAttribute(): string
    {
        return bcmul((string) $this->daily_price, (string) GarageBatch::MONTHLY_PRICE_MULTIPLIER, 2);
    }
}
