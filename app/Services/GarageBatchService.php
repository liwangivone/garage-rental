<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\GarageBatch;
use App\Models\GaragePrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GarageBatchService
{
    /**
     * Buat sekelompok garasi dengan kode berurutan per tipe (S001, C010, E003, ...).
     *
     * @throws ValidationException bila total garasi satu tipe akan melebihi 999
     */
    public function create(User $admin, GarageType $type, int $quantity, string $dailyPrice, ?string $electricityPrice): GarageBatch
    {
        return DB::transaction(function () use ($admin, $type, $quantity, $dailyPrice, $electricityPrice) {
            $start = Garage::nextSequence($type);

            if ($start + $quantity - 1 > Garage::MAX_PER_TYPE) {
                $left = max(Garage::MAX_PER_TYPE - ($start - 1), 0);
                throw ValidationException::withMessages([
                    'quantity' => "Kode garasi hanya 3 digit. Sisa kuota {$type->label()}: {$left} garasi.",
                ]);
            }

            $batch = GarageBatch::create([
                'type' => $type,
                'quantity' => $quantity,
                'daily_price' => $dailyPrice,
                'electricity_price_per_kwh' => $type->hasElectricityPrice() ? $electricityPrice : null,
                'created_by' => $admin->id,
            ]);

            // Satu harga per tipe: harga yang diisi di sini menjadi harga berlaku untuk seluruh
            // garasi tipe ini pada sewa berikutnya (sewa lama tetap memakai harga saat transaksi).
            GaragePrice::updateOrCreate(
                ['type' => $type],
                [
                    'daily_price' => $dailyPrice,
                    'electricity_price_per_kwh' => $type->hasElectricityPrice() ? $electricityPrice : null,
                    'updated_by' => $admin->id,
                ],
            );

            $now = now();
            $rows = [];
            for ($i = 0; $i < $quantity; $i++) {
                $seq = $start + $i;
                $rows[] = [
                    'garage_batch_id' => $batch->id,
                    'type' => $type->value,
                    'sequence' => $seq,
                    'code' => Garage::makeCode($type, $seq),
                    'status' => GarageStatus::Available->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            Garage::insert($rows);

            return $batch;
        });
    }
}
