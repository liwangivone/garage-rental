<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\GaragePrice;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Format;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sewa baru oleh penyewa.
 *
 * - Tanggal mulai dipilih penyewa: tidak boleh sebelum hari ini dan maksimal 30 hari ke depan.
 * - Bukti bayar diverifikasi manual oleh admin; sewa yang belum diverifikasi tetap menahan garasi.
 * - Harian: 1..(hari bulan - 1), maksimal 30 hari. Bulanan: tepat satu bulan.
 *   Sewa lebih lama dibuat sebagai transaksi terpisah.
 * - Biaya listrik (mobil listrik di garasi mobil listrik) = kapasitas kWh x harga/kWh,
 *   sekali bayar di awal dan masuk ke total.
 */
class RentalBookingService
{
    public const MAX_DAILY_DAYS = 30;
    public const MAX_LISTED_GARAGES = 200;
    public const MAX_ADVANCE_DAYS = 30;

    public static function maxDailyDays(Carbon $start): int
    {
        return min(self::MAX_DAILY_DAYS, RentalPeriodCalculator::monthlyThreshold($start) - 1);
    }

    /**
     * Hitung tanggal selesai, daftar garasi tersedia, dan biaya. Tidak melempar exception;
     * masalah input dikembalikan di 'errors'. Dipakai oleh pratinjau (AJAX) dan oleh book().
     *
     * @param  array<string, mixed>  $in  rental_type, days, start_date, garage_type, garage_id,
     *                                    vehicle_id | vehicle_type + battery_capacity_kwh
     * @return array<string, mixed>
     */
    public function quote(User $user, array $in): array
    {
        $errors = [];
        $out = [
            'errors' => [], 'start' => null, 'end' => null, 'duration_days' => null,
            'breakdown' => null, 'allowed_garage_types' => [], 'garages' => [],
            'garage' => null, 'price' => null, 'vehicle_type' => null, 'costs' => null, 'max_daily_days' => null,
        ];

        // Kendaraan
        $vehicle = null;
        $vehicleType = null;
        $battery = null;
        if (! empty($in['vehicle_id'])) {
            $vehicle = Vehicle::where('user_id', $user->id)->find($in['vehicle_id']);
            if (! $vehicle) {
                $errors['vehicle_id'] = 'Kendaraan tidak ditemukan.';
            } else {
                $vehicleType = $vehicle->type;
                $battery = $vehicle->battery_capacity_kwh;
            }
        } else {
            $vehicleType = VehicleType::tryFrom((string) ($in['vehicle_type'] ?? ''));
            $battery = isset($in['battery_capacity_kwh']) && is_numeric($in['battery_capacity_kwh']) ? (string) $in['battery_capacity_kwh'] : null;
        }
        $out['vehicle_type'] = $vehicleType;
        $out['allowed_garage_types'] = $vehicleType?->allowedGarageTypes() ?? [];

        // Periode
        $start = null;
        try {
            $start = ! empty($in['start_date']) ? Carbon::parse($in['start_date'])->startOfDay() : null;
        } catch (\Throwable) {
            $errors['start_date'] = 'Tanggal mulai tidak valid.';
        }

        $rentalType = RentalType::tryFrom((string) ($in['rental_type'] ?? ''));
        $end = null;

        if ($start && ! RentalPeriodCalculator::isValidStartDate($start)) {
            $errors['start_date'] = 'Tanggal mulai tidak boleh sebelum hari ini.';
            $start = null;
        }

        if ($start && $start->gt(today()->addDays(self::MAX_ADVANCE_DAYS))) {
            $errors['start_date'] = 'Tanggal mulai maksimal '.self::MAX_ADVANCE_DAYS.' hari dari hari ini.';
            $start = null;
        }

        if ($start && $rentalType) {
            $out['max_daily_days'] = self::maxDailyDays($start);

            if ($rentalType === RentalType::Daily) {
                $days = (int) ($in['days'] ?? 0);
                if ($days < 1 || $days > $out['max_daily_days']) {
                    $errors['days'] = "Sewa harian 1–{$out['max_daily_days']} hari untuk bulan ini. Untuk lebih lama, pilih sewa bulanan.";
                } else {
                    $end = RentalPeriodCalculator::endDateForDays($start, $days);
                }
            } else {
                $end = RentalPeriodCalculator::earliestMonthlyEndDate($start);
            }
        }

        $out['start'] = $start;
        $out['end'] = $end;

        if ($start && $end) {
            $out['duration_days'] = RentalPeriodCalculator::durationInDays($start, $end);
            $out['breakdown'] = RentalPeriodCalculator::breakdown($start, $end);
        }

        // Garasi
        $garageType = GarageType::tryFrom((string) ($in['garage_type'] ?? ''));
        if ($garageType && $vehicleType && ! $vehicleType->canUseGarage($garageType)) {
            $errors['garage_type'] = "{$vehicleType->label()} tidak dapat memakai {$garageType->label()}.";
            $garageType = null;
        }

        if ($start && $end && $garageType) {
            $out['price'] = GaragePrice::forType($garageType);
            $out['garages'] = Garage::query()
                ->where('type', $garageType)
                ->availableBetween($start, $end)
                ->orderBy('sequence')->limit(self::MAX_LISTED_GARAGES)->get();
        }

        if (! empty($in['garage_id']) && $start && $end) {
            $garage = Garage::find($in['garage_id']);
            if (! $garage) {
                $errors['garage_id'] = 'Garasi tidak ditemukan.';
            } elseif ($vehicleType && ! $vehicleType->canUseGarage($garage->type)) {
                $errors['garage_id'] = "{$vehicleType->label()} tidak dapat memakai {$garage->type->label()}.";
            } elseif (! $garage->isFreeBetween($start, $end)) {
                $errors['garage_id'] = 'Garasi ini sudah disewa pada tanggal tersebut. Pilih garasi lain.';
            } elseif ($vehicle && $vehicle->hasRentalBetween($start, $end)) {
                $errors['vehicle_id'] = 'Kendaraan ini sudah punya sewa pada tanggal tersebut. Sewa berikutnya harus mulai setelah sewa sebelumnya selesai.';
            } else {
                $price = GaragePrice::forType($garage->type);
                if (! $price) {
                    $errors['garage_id'] = 'Harga garasi belum diatur admin.';
                } else {
                    $out['garage'] = $garage;
                    $out['price'] = $price;
                    $out['costs'] = $this->costs($garage, $price, $start, $end, $vehicleType, $battery);
                }
            }
        }

        $out['errors'] = $errors;

        return $out;
    }

    /** @return array{rental_cost:string, electricity_cost:string, total:string, electricity_price_per_kwh:?string} */
    private function costs(Garage $garage, GaragePrice $price, Carbon $start, Carbon $end, ?VehicleType $vehicleType, ?string $battery): array
    {
        $rentalCost = RentalPeriodCalculator::rentalCost($start, $end, (string) $price->daily_price);
        $electricity = '0.00';

        if ($garage->type === GarageType::ElectricCar && $vehicleType === VehicleType::ElectricCar && $battery) {
            $electricity = bcmul((string) $battery, (string) $price->electricity_price_per_kwh, 2);
        }

        return [
            'rental_cost' => $rentalCost,
            'electricity_cost' => $electricity,
            'total' => bcadd($rentalCost, $electricity, 2),
            'electricity_price_per_kwh' => $price->electricity_price_per_kwh,
        ];
    }

    /** Bentuk JSON untuk pratinjau di form. */
    public function quoteForJson(User $user, array $in): array
    {
        $q = $this->quote($user, $in);
        $costs = $q['costs'];

        return [
            'errors' => $q['errors'],
            'max_daily_days' => $q['max_daily_days'],
            'allowed_garage_types' => array_map(fn (GarageType $t) => $t->value, $q['allowed_garage_types']),
            'end_date' => $q['end']?->toDateString(),
            'end_date_label' => $q['end']?->translatedFormat('l, j F Y'),
            'duration_days' => $q['duration_days'],
            'breakdown' => $q['breakdown'],
            'garages' => collect($q['garages'])->map(fn (Garage $g) => [
                'id' => $g->id,
                'code' => $g->code,
                'daily_price' => $q['price'] ? Format::rupiah($q['price']->daily_price) : null,
                'monthly_price' => $q['price'] ? Format::rupiah($q['price']->monthly_price) : null,
                'electricity_price' => $q['price']?->electricity_price_per_kwh ? Format::rupiah($q['price']->electricity_price_per_kwh) : null,
            ])->values(),
            'costs' => $costs ? [
                'rental_cost' => Format::rupiah($costs['rental_cost']),
                'electricity_cost' => Format::rupiah($costs['electricity_cost']),
                'has_electricity' => bccomp($costs['electricity_cost'], '0', 2) > 0,
                'total' => Format::rupiah($costs['total']),
            ] : null,
        ];
    }

    /**
     * Simpan sewa baru. Data kendaraan baru ($vehicleData) dipakai bila 'vehicle_id' kosong.
     *
     * @param  array<string, mixed>  $in
     * @param  array<string, mixed>  $vehicleData  type, plate_number, brand, model, battery_capacity_kwh
     *
     * @throws ValidationException
     */
    public function book(User $user, array $in, array $vehicleData, UploadedFile $proof): Rental
    {
        return DB::transaction(function () use ($user, $in, $vehicleData, $proof) {
            // Kunci baris garasi supaya dua penyewa tidak memesan garasi yang sama bersamaan.
            if (! empty($in['garage_id'])) {
                Garage::whereKey($in['garage_id'])->lockForUpdate()->first();
            }

            $q = $this->quote($user, $in);

            if (! $q['costs'] && ! isset($q['errors']['garage_id'])) {
                $q['errors']['garage_id'] = 'Pilih garasi yang tersedia.';
            }
            if ($q['errors']) {
                throw ValidationException::withMessages($q['errors']);
            }

            /** @var Garage $garage */
            $garage = $q['garage'];
            $costs = $q['costs'];
            $start = $q['start'];
            $end = $q['end'];

            $vehicle = ! empty($in['vehicle_id'])
                ? Vehicle::where('user_id', $user->id)->findOrFail($in['vehicle_id'])
                : $user->vehicles()->create($vehicleData);

            $price = $q['price'];
            $breakdown = $q['breakdown'];
            $rental = Rental::create([
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'garage_id' => $garage->id,
                'rental_type' => $breakdown['monthly'] > 0 ? RentalType::Monthly : RentalType::Daily,
                'start_date' => $start,
                'end_date' => $end,
                'duration_days' => $q['duration_days'],
                'monthly_count' => $breakdown['monthly'],
                'daily_count' => $breakdown['daily'],
                'daily_price' => $price->daily_price,
                'monthly_price' => $price->monthly_price,
                'electricity_price_per_kwh' => $costs['electricity_price_per_kwh'],
                'electricity_cost' => $costs['electricity_cost'],
                'rental_cost' => $costs['rental_cost'],
                'total_price' => $costs['total'],
                'payment_proof_path' => $proof->store('payment-proofs', 'public'),
                'payment_status' => PaymentStatus::Pending,
            ]);

            // 'used' hanya bila sewa sudah berjalan hari ini; sewa mendatang tetap 'available'.
            $garage->syncStatus();

            return $rental;
        });
    }
}
