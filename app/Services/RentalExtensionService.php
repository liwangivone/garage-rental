<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RentalExtensionService
{
    /**
     * Hitung biaya baru bila sewa diperpanjang sampai $newEnd (aturan sewa baru
     * dari tanggal mulai asli). Biaya listrik masih PENDING, jadi nilai tersimpan dipertahankan.
     *
     * @return array{breakdown: array{monthly:int,daily:int}, duration_days:int, rental_cost:string, previous_total:string, new_total:string, additional_cost:string}
     */
    public function preview(Rental $rental, Carbon $newEnd): array
    {
        $rentalCost = RentalPeriodCalculator::rentalCost($rental->start_date, $newEnd, (string) $rental->daily_price);
        $newTotal = bcadd($rentalCost, (string) $rental->electricity_cost, 2);

        return [
            'breakdown' => RentalPeriodCalculator::breakdown($rental->start_date, $newEnd),
            'duration_days' => RentalPeriodCalculator::durationInDays($rental->start_date, $newEnd),
            'rental_cost' => $rentalCost,
            'previous_total' => (string) $rental->total_price,
            'new_total' => $newTotal,
            'additional_cost' => bcsub($newTotal, (string) $rental->total_price, 2),
        ];
    }

    public function extend(Rental $rental, Carbon $newEnd, UploadedFile $proof, User $admin): RentalExtension
    {
        $path = $proof->store('extension-proofs', 'public');
        $calc = $this->preview($rental, $newEnd);

        return DB::transaction(function () use ($rental, $newEnd, $admin, $path, $calc) {
            $extension = RentalExtension::create([
                'rental_id' => $rental->id,
                'processed_by' => $admin->id,
                'previous_end_date' => $rental->end_date,
                'new_end_date' => $newEnd,
                'previous_rental_type' => $rental->rental_type,
                'new_rental_type' => $calc['breakdown']['monthly'] > 0 ? 'monthly' : 'daily',
                'previous_total_price' => $calc['previous_total'],
                'new_total_price' => $calc['new_total'],
                'additional_cost' => $calc['additional_cost'],
                'payment_proof_path' => $path,
            ]);

            $rental->update([
                'end_date' => $newEnd,
                'duration_days' => $calc['duration_days'],
                'monthly_count' => $calc['breakdown']['monthly'],
                'daily_count' => $calc['breakdown']['daily'],
                'rental_type' => $calc['breakdown']['monthly'] > 0 ? 'monthly' : 'daily',
                'rental_cost' => $calc['rental_cost'],
                'total_price' => $calc['new_total'],
            ]);

            return $extension;
        });
    }
}
