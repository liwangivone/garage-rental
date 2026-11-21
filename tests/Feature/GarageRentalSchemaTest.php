<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\RegistrationStatus;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\GarageBatch;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageRentalSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_admin_and_is_idempotent(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $admin = User::where('role', UserRole::Admin)->sole();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isApproved());
        $this->assertNull($admin->nik);
    }

    public function test_new_penyewa_defaults_to_pending(): void
    {
        $user = User::factory()->create(['nik' => '7371010101010001', 'phone' => '0811']);

        $this->assertSame(UserRole::Penyewa, $user->fresh()->role);
        $this->assertSame(RegistrationStatus::Pending, $user->fresh()->registration_status);
        $this->assertCount(1, User::pendingVerification()->get());
    }

    public function test_vehicle_garage_compatibility(): void
    {
        $this->assertTrue(VehicleType::Motorcycle->canUseGarage(GarageType::Bicycle));
        $this->assertFalse(VehicleType::Motorcycle->canUseGarage(GarageType::Car));
        $this->assertTrue(VehicleType::Car->canUseGarage(GarageType::Car));
        $this->assertFalse(VehicleType::Car->canUseGarage(GarageType::ElectricCar));
        $this->assertTrue(VehicleType::ElectricCar->canUseGarage(GarageType::Car));
        $this->assertTrue(VehicleType::ElectricCar->canUseGarage(GarageType::ElectricCar));
    }

    public function test_garage_codes_and_monthly_price(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $batch = GarageBatch::create([
            'type' => GarageType::Bicycle, 'quantity' => 2, 'daily_price' => 10000, 'created_by' => $admin->id,
        ]);
        foreach ([1, 2] as $i) {
            $seq = Garage::nextSequence(GarageType::Bicycle);
            Garage::create([
                'garage_batch_id' => $batch->id, 'type' => GarageType::Bicycle,
                'sequence' => $seq, 'code' => Garage::makeCode(GarageType::Bicycle, $seq),
            ]);
        }

        $this->assertSame(['S001', 'S002'], Garage::orderBy('sequence')->pluck('code')->all());
        $this->assertSame('E010', Garage::makeCode(GarageType::ElectricCar, 10));
        $this->assertSame('250000.00', $batch->monthly_price);
        $this->assertSame('250000.00', Garage::first()->monthly_price);
        $this->assertSame(GarageStatus::Available, Garage::first()->status);
    }

    public function test_rental_relations_and_availability(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $penyewa = User::factory()->create();
        $batch = GarageBatch::create([
            'type' => GarageType::ElectricCar, 'quantity' => 2, 'daily_price' => 50000,
            'electricity_price_per_kwh' => 2500, 'created_by' => $admin->id,
        ]);
        $g1 = Garage::create(['garage_batch_id' => $batch->id, 'type' => GarageType::ElectricCar, 'sequence' => 1, 'code' => 'E001']);
        $g2 = Garage::create(['garage_batch_id' => $batch->id, 'type' => GarageType::ElectricCar, 'sequence' => 2, 'code' => 'E002']);
        $vehicle = Vehicle::create([
            'user_id' => $penyewa->id, 'type' => VehicleType::ElectricCar, 'plate_number' => 'DD 1234 AB',
            'brand' => 'Hyundai', 'model' => 'Ioniq 5', 'battery_capacity_kwh' => 58,
        ]);
        $rental = Rental::create([
            'user_id' => $penyewa->id, 'vehicle_id' => $vehicle->id, 'garage_id' => $g1->id,
            'rental_type' => RentalType::Daily, 'start_date' => today(), 'end_date' => today()->addDays(2),
            'duration_days' => 3, 'daily_price' => 50000, 'monthly_price' => 1250000,
            'electricity_price_per_kwh' => 2500, 'electricity_cost' => 145000,
            'rental_cost' => 150000, 'total_price' => 295000, 'payment_proof_path' => 'proofs/a.jpg',
        ]);
        RentalExtension::create([
            'rental_id' => $rental->id, 'processed_by' => $admin->id,
            'previous_end_date' => today()->addDays(2), 'new_end_date' => today()->addDays(3),
            'previous_rental_type' => RentalType::Daily, 'new_rental_type' => RentalType::Daily,
            'previous_total_price' => 295000, 'new_total_price' => 345000, 'additional_cost' => 50000,
            'payment_proof_path' => 'proofs/b.jpg',
        ]);

        $this->assertTrue($rental->penyewa->is($penyewa));
        $this->assertTrue($rental->garage->batch->is($batch));
        $this->assertTrue($rental->vehicle->owner->is($penyewa));
        $this->assertCount(1, $rental->extensions);
        $this->assertTrue($rental->extensions->first()->admin->is($admin));
        $this->assertCount(1, $penyewa->rentals);
        $this->assertTrue($g1->currentRental->is($rental));

        $this->assertSame(['E002'], Garage::availableOn()->pluck('code')->all());
        $this->assertSame(['E001'], Garage::rentedOn()->pluck('code')->all());
        $this->assertSame(['E001', 'E002'], Garage::availableOn(today()->addDays(10))->orderBy('code')->pluck('code')->all());
        $this->assertSame(['E001', 'E002'], Garage::forVehicleType(VehicleType::ElectricCar)->orderBy('code')->pluck('code')->all());
        $this->assertCount(0, Garage::forVehicleType(VehicleType::Car)->get());
    }

    public function test_overlap_checks_for_second_rental_and_extension(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $penyewa = User::factory()->create();
        $batch = GarageBatch::create(['type' => GarageType::Car, 'quantity' => 1, 'daily_price' => 100000, 'created_by' => $admin->id]);
        $garage = Garage::create(['garage_batch_id' => $batch->id, 'type' => GarageType::Car, 'sequence' => 1, 'code' => 'C001']);
        $vehicle = Vehicle::create(['user_id' => $penyewa->id, 'type' => VehicleType::Car, 'plate_number' => 'DD 1 A', 'brand' => 'Toyota', 'model' => 'Avanza']);

        $make = fn ($start, $end) => Rental::create([
            'user_id' => $penyewa->id, 'vehicle_id' => $vehicle->id, 'garage_id' => $garage->id,
            'rental_type' => RentalType::Monthly, 'start_date' => $start, 'end_date' => $end,
            'duration_days' => 31, 'monthly_count' => 1, 'daily_count' => 0,
            'daily_price' => 100000, 'monthly_price' => 2500000, 'rental_cost' => 2500000,
            'total_price' => 2500000, 'payment_proof_path' => 'p.jpg',
        ]);

        // sewa pertama: besok sampai +30 hari, dipesan lebih awal (start bukan hari ini)
        $first = $make(today()->addDay(), today()->addDays(31));

        // sewa kedua harus mulai setelah sewa pertama selesai
        $this->assertTrue($vehicle->hasRentalBetween(today()->addDays(31), today()->addDays(40)));
        $this->assertFalse($vehicle->hasRentalBetween(today()->addDays(32), today()->addDays(40)));
        $this->assertFalse($garage->isFreeBetween(today()->addDays(31), today()->addDays(40)));
        $this->assertTrue($garage->isFreeBetween(today()->addDays(32), today()->addDays(40)));

        // perpanjangan: sewa itu sendiri diabaikan, tapi sewa lain di garasi sama tetap bentrok
        $this->assertTrue($garage->isFreeBetween(today()->addDay(), today()->addDays(60), $first->id));
        $vehicle2 = Vehicle::create(['user_id' => $penyewa->id, 'type' => VehicleType::Car, 'plate_number' => 'DD 2 A', 'brand' => 'Honda', 'model' => 'Brio']);
        Rental::create([
            'user_id' => $penyewa->id, 'vehicle_id' => $vehicle2->id, 'garage_id' => $garage->id,
            'rental_type' => RentalType::Daily, 'start_date' => today()->addDays(40), 'end_date' => today()->addDays(42),
            'duration_days' => 3, 'daily_count' => 3, 'daily_price' => 100000, 'monthly_price' => 2500000,
            'rental_cost' => 300000, 'total_price' => 300000, 'payment_proof_path' => 'q.jpg',
        ]);
        $this->assertFalse($garage->isFreeBetween(today()->addDay(), today()->addDays(60), $first->id));
        $this->assertTrue($garage->isFreeBetween(today()->addDay(), today()->addDays(39), $first->id));

        // canExtendTo: ditolak bila memotong sewa lain / tidak memperpanjang
        $first->load('garage');
        $this->assertTrue($first->canExtendTo(today()->addDays(39)));
        $this->assertFalse($first->canExtendTo(today()->addDays(40)));
        $this->assertFalse($first->canExtendTo(today()->addDays(60)));
        $this->assertFalse($first->canExtendTo(today()->addDays(31)));
    }
}
