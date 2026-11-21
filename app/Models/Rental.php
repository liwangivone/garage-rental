<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\RentalType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'vehicle_id', 'garage_id', 'rental_type', 'start_date', 'end_date', 'duration_days', 'monthly_count', 'daily_count',
    'daily_price', 'monthly_price', 'electricity_price_per_kwh', 'electricity_cost',
    'rental_cost', 'total_price', 'payment_proof_path',
    'payment_status', 'payment_rejection_reason', 'payment_verified_at', 'payment_verified_by',
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
            'payment_status' => PaymentStatus::class,
            'payment_verified_at' => 'datetime',
        ];
    }

    /** Sewa yang masih menempati garasi: belum ditolak pembayarannya. */
    public function scopeOccupying($query)
    {
        return $query->where('payment_status', '!=', PaymentStatus::Rejected->value);
    }

    public function scopeApproved($query)
    {
        return $query->where('payment_status', PaymentStatus::Approved->value);
    }

    public function paymentVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }

    public function isPaymentApproved(): bool
    {
        return $this->payment_status === PaymentStatus::Approved;
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

        return $this->extensionError($newEnd) === null;
    }

    /** Sewa yang sudah berakhir atau belum terverifikasi tidak bisa diperpanjang. */
    public function extendableError(): ?string
    {
        if (! $this->isPaymentApproved()) {
            return 'Verifikasi bukti bayar sewa ini terlebih dahulu.';
        }

        if ($this->end_date->lt(today())) {
            return 'Sewa yang sudah berakhir tidak dapat diperpanjang. Penyewa perlu membuat sewa baru.';
        }

        return null;
    }

    /** Alasan perpanjangan ditolak, atau null bila boleh. */
    public function extensionError(\Carbon\CarbonInterface $newEndDate): ?string
    {
        $newEnd = \Illuminate\Support\Carbon::instance($newEndDate)->startOfDay();

        if ($error = $this->extendableError()) {
            return $error;
        }

        if (! $newEnd->gt($this->end_date)) {
            return 'Tanggal selesai baru harus setelah tanggal selesai saat ini.';
        }

        if (! $this->garage->isFreeBetween($this->start_date, $newEnd, $this->id)) {
            return 'Tanggal tersebut bentrok dengan sewa lain di garasi ini.';
        }

        if ($this->vehicle->hasRentalBetween($this->start_date, $newEnd, $this->id)) {
            return 'Tanggal tersebut bentrok dengan sewa lain milik kendaraan yang sama.';
        }

        return null;
    }

    /** upcoming | active | finished (berdasarkan tanggal hari ini). */
    public function phase(): string
    {
        if ($this->start_date->gt(today())) {
            return 'upcoming';
        }

        return $this->end_date->lt(today()) ? 'finished' : 'active';
    }

    public function isActiveOn(?\DateTimeInterface $date = null): bool
    {
        $date = \Illuminate\Support\Carbon::instance($date ?? now())->startOfDay();

        return $this->start_date->lte($date) && $this->end_date->gte($date);
    }
}
