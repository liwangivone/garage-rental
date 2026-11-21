<?php

namespace Tests\Concerns;

use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\GarageBatch;
use App\Models\GaragePrice;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalPeriodCalculator as Calc;
use Illuminate\Support\Carbon;

/** Pembuat data uji yang ringkas untuk unit test yang memakai database. */
trait BuildsFixtures
{
    /** Hari ini yang dibekukan agar test deterministik (Sabtu, 10 Oktober 2026). */
    protected function freezeToday(string $date = '2026-10-10 10:00:00'): void
    {
        $this->travelTo(Carbon::parse($date));
    }

    protected function adminUser(): User
    {
        return User::where('role', UserRole::Admin)->first()
            ?? User::factory()->create(['role' => UserRole::Admin, 'registration_status' => RegistrationStatus::Approved]);
    }

    protected function penyewaUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['registration_status' => RegistrationStatus::Approved], $attrs));
    }

    /** Membuat garasi (dan harga tipenya bila belum ada). */
    protected function garage(GarageType $type = GarageType::Car, int $seq = 1, string $daily = '100000', ?string $kwh = null): Garage
    {
        $admin = $this->adminUser();

        if (! GaragePrice::forType($type)) {
            GaragePrice::create([
                'type' => $type, 'daily_price' => $daily, 'updated_by' => $admin->id,
                'electricity_price_per_kwh' => $type->hasElectricityPrice() ? ($kwh ?? '3000') : null,
            ]);
        }

        $batch = GarageBatch::create([
            'type' => $type, 'quantity' => 1, 'daily_price' => $daily, 'created_by' => $admin->id,
            'electricity_price_per_kwh' => $type->hasElectricityPrice() ? ($kwh ?? '3000') : null,
        ]);

        return Garage::create([
            'garage_batch_id' => $batch->id, 'type' => $type, 'sequence' => $seq, 'code' => Garage::makeCode($type, $seq),
        ]);
    }

    protected function vehicle(?User $owner = null, VehicleType $type = VehicleType::Car, array $attrs = []): Vehicle
    {
        $owner ??= $this->penyewaUser();

        return Vehicle::create(array_merge([
            'user_id' => $owner->id, 'type' => $type,
            'plate_number' => $type->requiresPlateNumber() ? 'DD '.fake()->unique()->numerify('####').' AB' : null,
            'brand' => 'Merek', 'model' => 'Model',
            'battery_capacity_kwh' => $type->requiresBatteryCapacity() ? 50 : null,
        ], $attrs));
    }

    /** Sewa dengan biaya dihitung oleh RentalPeriodCalculator dari harga tipe garasi. */
    protected function rental(Garage $garage, Vehicle $vehicle, string $start, string $end, PaymentStatus $status = PaymentStatus::Approved, array $attrs = []): Rental
    {
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        $price = GaragePrice::forType($garage->type);
        $parts = Calc::breakdown($s, $e);
        $cost = Calc::rentalCost($s, $e, (string) $price->daily_price);

        return Rental::create(array_merge([
            'user_id' => $vehicle->user_id, 'vehicle_id' => $vehicle->id, 'garage_id' => $garage->id,
            'rental_type' => $parts['monthly'] > 0 ? RentalType::Monthly : RentalType::Daily,
            'start_date' => $s, 'end_date' => $e, 'duration_days' => Calc::durationInDays($s, $e),
            'monthly_count' => $parts['monthly'], 'daily_count' => $parts['daily'],
            'daily_price' => $price->daily_price, 'monthly_price' => $price->monthly_price,
            'electricity_price_per_kwh' => $price->electricity_price_per_kwh, 'electricity_cost' => 0,
            'rental_cost' => $cost, 'total_price' => $cost,
            'payment_proof_path' => 'proofs/contoh.jpg', 'payment_status' => $status,
        ], $attrs));
    }
}
