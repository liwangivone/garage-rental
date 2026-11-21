<?php

namespace App\Models;

use App\Enums\RentalType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'vehicle_id', 'garage_id', 'rental_type', 'start_date', 'end_date', 'duration_days', 'monthly_count', 'daily_count',
    'daily_price', 'monthly_price', 'electricity_price_per_kwh', 'electricity_cost',
    'rental_cost', 'total_price', 'payment_proof_path',
])]
class Rental extends Model
{
    protected function casts(): array
    {
        return [
            'rental_type' => RentalType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_days' => 'integer',
            'monthly_count' => 'integer',
            'daily_count' => 'integer',
            'daily_price' => 'decimal:2',
            'monthly_price' => 'decimal:2',
            'electricity_price_per_kwh' => 'decimal:2',
            'electricity_cost' => 'decimal:2',
            'rental_cost' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function penyewa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class);
    }

    /**
     * Perpanjangan hanya sah bila tanggal selesai baru lebih akhir dan garasi tidak
     * bentrok dengan sewa lain. Tidak ada jalur paksa (override) oleh admin.
     */
    public function canExtendTo(\Carbon\CarbonInterface $newEndDate): bool
    {
        $newEnd = \Illuminate\Support\Carbon::instance($newEndDate)->startOfDay();

        return $newEnd->gt($this->end_date)
            && $this->garage->isFreeBetween($this->start_date, $newEnd, $this->id);
    }

    public function isActiveOn(?\DateTimeInterface $date = null): bool
    {
        $date = \Illuminate\Support\Carbon::instance($date ?? now())->startOfDay();

        return $this->start_date->lte($date) && $this->end_date->gte($date);
    }
}
