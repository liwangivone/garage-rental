<?php

namespace App\Models;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'type', 'plate_number', 'brand', 'model', 'battery_capacity_kwh'])]
class Vehicle extends Model
{
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'battery_capacity_kwh' => 'decimal:2',
        ];
    }

    public function canUseGarageType(GarageType $garageType): bool
    {
        return $this->type->canUseGarage($garageType);
    }

    /**
     * Satu kendaraan tidak boleh disewa dua kali pada tanggal yang sama; sewa kedua
     * harus mulai setelah sewa pertama selesai.
     */
    public function hasRentalBetween(CarbonInterface $start, CarbonInterface $end, ?int $ignoreRentalId = null): bool
    {
        return $this->rentals()
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->when($ignoreRentalId, fn ($q) => $q->where('id', '!=', $ignoreRentalId))
            ->exists();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
