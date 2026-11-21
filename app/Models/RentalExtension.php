<?php

namespace App\Models;

use App\Enums\RentalType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rental_id', 'processed_by', 'previous_end_date', 'new_end_date',
    'previous_rental_type', 'new_rental_type', 'previous_total_price',
    'new_total_price', 'additional_cost', 'electricity_cost', 'payment_proof_path',
])]
class RentalExtension extends Model
{
    protected function casts(): array
    {
        return [
            'previous_end_date' => 'date',
            'new_end_date' => 'date',
            'previous_rental_type' => RentalType::class,
            'new_rental_type' => RentalType::class,
            'previous_total_price' => 'decimal:2',
            'new_total_price' => 'decimal:2',
            'additional_cost' => 'decimal:2',
            'electricity_cost' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
