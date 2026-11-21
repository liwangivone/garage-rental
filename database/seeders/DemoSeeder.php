<?php

namespace Database\Seeders;

use App\Enums\GarageType;
use App\Enums\RegistrationStatus;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageBatchService;
use App\Services\RentalPeriodCalculator as Calc;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba halaman admin. TIDAK ikut `db:seed` biasa.
 * Jalankan: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(GarageBatchService $garages): void
    {
        $this->call(AdminSeeder::class);

        if (User::where('email', 'like', 'demo.%')->exists()) {
            $this->command?->warn('Data demo sudah ada, dilewati.');

            return;
        }

        $admin = User::where('role', UserRole::Admin)->firstOrFail();

        $garages->create($admin, GarageType::Bicycle, 5, '15000', null);
        $garages->create($admin, GarageType::Car, 6, '75000', null);
        $garages->create($admin, GarageType::ElectricCar, 3, '90000', '3500');

        $mk = fn (string $name, string $nik, RegistrationStatus $status, ?string $reason = null) => User::create([
            'name' => $name, 'nik' => $nik, 'phone' => '0812'.substr($nik, -8),
            'email' => 'demo.'.str($name)->slug('.')->value().'@example.test',
            'password' => 'password', 'role' => UserRole::Penyewa,
            'registration_status' => $status, 'rejection_reason' => $reason,
            'verified_at' => $status === RegistrationStatus::Pending ? null : now(),
            'verified_by' => $status === RegistrationStatus::Pending ? null : $admin->id,
        ]);

        $andi = $mk('Andi Pratama', '7371010101900001', RegistrationStatus::Approved);
        $sari = $mk('Sari Wulandari', '7371010202910002', RegistrationStatus::Approved);
        $budi = $mk('Budi Santoso', '7371010303920003', RegistrationStatus::Approved);
        $mk('Citra Lestari', '7371010404930004', RegistrationStatus::Pending);
        $mk('Dimas Saputra', '7371010505940005', RegistrationStatus::Pending);
        $mk('Eka Putri', '7371010606950006', RegistrationStatus::Pending);
        $mk('Fajar Nugroho', '7371010707960007', RegistrationStatus::Rejected, 'Foto KTP tidak terbaca');

        $car = Vehicle::create(['user_id' => $andi->id, 'type' => VehicleType::Car, 'plate_number' => 'DD 1234 AB', 'brand' => 'Toyota', 'model' => 'Avanza']);
        $moto = Vehicle::create(['user_id' => $sari->id, 'type' => VehicleType::Motorcycle, 'plate_number' => 'DD 5678 CD', 'brand' => 'Honda', 'model' => 'Vario 160']);
        $ev = Vehicle::create(['user_id' => $budi->id, 'type' => VehicleType::ElectricCar, 'plate_number' => 'DD 9012 EF', 'brand' => 'Hyundai', 'model' => 'Ioniq 5', 'battery_capacity_kwh' => 58]);
        $bike = Vehicle::create(['user_id' => $andi->id, 'type' => VehicleType::Bicycle, 'brand' => 'Polygon', 'model' => 'Strattos']);

        $g = fn (GarageType $t, int $seq) => Garage::where('type', $t)->where('sequence', $seq)->firstOrFail();

        // Sedang berjalan hari ini
        $this->rental($andi, $car, $g(GarageType::Car, 1), today()->subDays(5), today()->addDays(2));            // segera berakhir
        $monthStart = today()->subDays(10);
        $this->rental($sari, $moto, $g(GarageType::Bicycle, 1), $monthStart, Calc::earliestMonthlyEndDate($monthStart)); // bulanan
        $this->rental($budi, $ev, $g(GarageType::ElectricCar, 1), today()->subDay(), today()->addDays(10));

        // Riwayat bulan-bulan sebelumnya (untuk laporan)
        $history = [[$andi, $car, GarageType::Car], [$sari, $moto, GarageType::Bicycle], [$budi, $ev, GarageType::ElectricCar], [$andi, $bike, GarageType::Bicycle]];
        foreach (range(1, 5) as $k) {
            foreach (array_slice($history, 0, 2 + ($k % 3)) as $i => [$user, $vehicle, $type]) {
                $start = today()->subMonths($k)->startOfMonth()->addDays(2 + $i * 6);
                $this->rental($user, $vehicle, $g($type, 2 + ($k % 2)), $start, Calc::endDateForDays($start, 3 + (($k + $i) * 2) % 9));
            }
        }
    }

    private function rental(User $user, Vehicle $vehicle, Garage $garage, CarbonInterface $start, CarbonInterface $end): Rental
    {
        $batch = $garage->batch;
        $parts = Calc::breakdown($start, $end);
        $cost = Calc::rentalCost($start, $end, (string) $batch->daily_price);

        return Rental::create([
            'user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'garage_id' => $garage->id,
            'rental_type' => $parts['monthly'] > 0 ? RentalType::Monthly : RentalType::Daily,
            'start_date' => $start, 'end_date' => $end,
            'duration_days' => Calc::durationInDays($start, $end),
            'monthly_count' => $parts['monthly'], 'daily_count' => $parts['daily'],
            'daily_price' => $batch->daily_price, 'monthly_price' => $batch->monthly_price,
            'electricity_price_per_kwh' => $batch->electricity_price_per_kwh,
            'electricity_cost' => 0, // rumus biaya listrik masih pending
            'rental_cost' => $cost, 'total_price' => $cost,
            'payment_proof_path' => 'demo/proof.jpg',
        ]);
    }
}
