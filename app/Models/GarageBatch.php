<?php

namespace App\Models;

use App\Enums\GarageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'quantity', 'daily_price', 'electricity_price_per_kwh', 'created_by'])]
class GarageBatch extends Model
{
    public const MONTHLY_PRICE_MULTIPLIER = 25;

    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'quantity' => 'integer',
            'daily_price' => 'decimal:2',
            'electricity_price_per_kwh' => 'decimal:2',
        ];
    }

    /** Harga bulanan = 25 x harga harian. */
    public function getMonthlyPriceAttribute(): string
    {
        return bcmul((string) $this->daily_price, (string) self::MONTHLY_PRICE_MULTIPLIER, 2);
    }

    public function garages(): HasMany
    {
        return $this->hasMany(Garage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
