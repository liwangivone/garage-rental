<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'nik', 'phone', 'email', 'password', 'role',
    'registration_status', 'rejection_reason', 'verified_at', 'verified_by',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'registration_status' => RegistrationStatus::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isPenyewa(): bool
    {
        return $this->role === UserRole::Penyewa;
    }

    public function isApproved(): bool
    {
        return $this->registration_status === RegistrationStatus::Approved;
    }

    /** Kendaraan milik penyewa. */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** Transaksi sewa garasi milik penyewa. */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /** Admin yang memverifikasi pendaftaran penyewa ini. */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(self::class, 'verified_by');
    }

    /** Kelompok garasi yang dibuat admin ini. */
    public function garageBatches(): HasMany
    {
        return $this->hasMany(GarageBatch::class, 'created_by');
    }

    public function scopePenyewa($query)
    {
        return $query->where('role', UserRole::Penyewa);
    }

    public function scopePendingVerification($query)
    {
        return $query->penyewa()->where('registration_status', RegistrationStatus::Pending);
    }
}
