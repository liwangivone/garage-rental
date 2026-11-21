<?php

namespace App\Models;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\VehicleType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['garage_batch_id', 'type', 'sequence', 'code', 'status'])]
class Garage extends Model
{
    /** Kode garasi hanya 3 digit, jadi maksimum 999 garasi per tipe. */
    public const MAX_PER_TYPE = 999;

    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'status' => GarageStatus::class,
            'sequence' => 'integer',
        ];
    }

    /** Format kode: prefix tipe + 3 digit, mis. S001, C010, E123. */
    public static function makeCode(GarageType $type, int $sequence): string
    {
        return $type->codePrefix().str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /** Nomor urut berikutnya untuk tipe tertentu (1 bila belum ada). */
    public static function nextSequence(GarageType $type): int
    {
        return ((int) static::where('type', $type)->max('sequence')) + 1;
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GarageBatch::class, 'garage_batch_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Sewa yang sedang berjalan pada hari ini. Filter harus dikirim ke subquery ofMany;
     * bila tidak, sewa dengan start_date terbesar (mis. sewa mendatang) terpilih lebih dulu
     * lalu tersaring habis, sehingga sewa yang sedang berjalan tidak terdeteksi.
     */
    public function currentRental(): HasOne
    {
        return $this->hasOne(Rental::class)->ofMany(
            ['start_date' => 'max'],
            fn ($query) => $query->occupying()
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today()),
        );
    }

    // Harga berlaku per tipe garasi (tabel garage_prices).
    public function getDailyPriceAttribute(): ?string
    {
        return GaragePrice::forType($this->type)?->daily_price;
    }

    public function getMonthlyPriceAttribute(): ?string
    {
        return GaragePrice::forType($this->type)?->monthly_price;
    }

    public function getElectricityPricePerKwhAttribute(): ?string
    {
        return GaragePrice::forType($this->type)?->electricity_price_per_kwh;
    }

    /**
     * Status mengikuti tanggal sewa: 'used' hanya bila ada sewa (belum ditolak) yang
     * sedang berjalan hari ini; sebelum tanggal mulai dan setelah selesai 'available'.
     */
    public function syncStatus(): void
    {
        $active = $this->rentals()->occupying()
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->exists();

        $status = $active ? GarageStatus::Used : GarageStatus::Available;
        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }

    /** Sinkronkan status seluruh garasi (dipakai scheduler harian). */
    public static function syncAllStatuses(): void
    {
        static::query()->rentedOn()->where('status', '!=', GarageStatus::Used)->update(['status' => GarageStatus::Used]);
        static::query()->notRentedOn()->where('status', '!=', GarageStatus::Available)->update(['status' => GarageStatus::Available]);
    }

    /** Garasi yang tidak punya sewa yang tumpang-tindih dengan rentang tanggal tertentu. */
    public function scopeAvailableBetween(
        Builder $query,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreRentalId = null,
    ): Builder {
        return $query->whereDoesntHave('rentals', function (Builder $q) use ($start, $end, $ignoreRentalId) {
            $q->occupying()
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start)
                ->when($ignoreRentalId, fn ($q) => $q->where('id', '!=', $ignoreRentalId));
        });
    }

    /** Apakah garasi ini kosong untuk rentang tersebut (abaikan sewa tertentu, mis. saat perpanjangan). */
    public function isFreeBetween(CarbonInterface $start, CarbonInterface $end, ?int $ignoreRentalId = null): bool
    {
        return static::whereKey($this->getKey())
            ->availableBetween($start, $end, $ignoreRentalId)
            ->exists();
    }

    /** Garasi tersedia pada tanggal tertentu (default hari ini). */
    public function scopeAvailableOn(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $date ??= today();

        return $query->availableBetween($date, $date);
    }

    /** Garasi yang tipenya cocok untuk jenis kendaraan tertentu. */
    public function scopeForVehicleType(Builder $query, VehicleType $vehicleType): Builder
    {
        return $query->whereIn('type', $vehicleType->allowedGarageTypes());
    }

    /** Garasi yang sedang disewa pada tanggal tertentu (untuk menu perpanjangan). */
    public function scopeRentedOn(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $date ??= today();

        return $query->whereHas('rentals', function (Builder $q) use ($date) {
            $q->occupying()->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
        });
    }

    public function scopeNotRentedOn(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $date ??= today();

        return $query->whereDoesntHave('rentals', function (Builder $q) use ($date) {
            $q->occupying()->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
        });
    }
}
