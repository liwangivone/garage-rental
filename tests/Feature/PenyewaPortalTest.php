<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\RegistrationStatus;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PenyewaPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'registration_status' => RegistrationStatus::Approved]);
        $svc = app(GarageBatchService::class);
        $svc->create($this->admin, GarageType::Bicycle, 2, '10000', null);
        $svc->create($this->admin, GarageType::Car, 2, '100000', null);
        $svc->create($this->admin, GarageType::ElectricCar, 2, '120000', '3000');
    }

    private function penyewa(RegistrationStatus $status = RegistrationStatus::Approved): User
    {
        return User::factory()->create(['registration_status' => $status, 'nik' => fake()->unique()->numerify('################')]);
    }

    private function garage(string $code): Garage
    {
        return Garage::where('code', $code)->firstOrFail();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'vehicle_type' => 'car', 'plate_number' => 'dd 1234 ab', 'brand' => 'Toyota', 'model' => 'Avanza',
            'rental_type' => 'daily', 'days' => 3, 'start_date' => today()->toDateString(),
            'garage_type' => 'car', 'garage_id' => $this->garage('C001')->id,
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ], $override);
    }

    public function test_registration_creates_pending_penyewa(): void
    {
        $this->post('/register', [
            'nik' => '7371010101900001', 'name' => 'Andi', 'phone' => '081234567890',
            'email' => 'andi@example.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/penyewa');

        $user = User::where('email', 'andi@example.test')->sole();
        $this->assertSame(UserRole::Penyewa, $user->role);
        $this->assertSame(RegistrationStatus::Pending, $user->registration_status);
        $this->assertAuthenticatedAs($user);
        $this->get('/penyewa')->assertOk()->assertSee('Menunggu verifikasi');
    }

    public function test_registration_validation(): void
    {
        $this->penyewa()->update(['nik' => '7371010101900001', 'email' => 'dup@example.test']);

        $this->post('/register', ['nik' => '123', 'name' => '', 'phone' => 'abc', 'email' => 'dup@example.test', 'password' => 'x', 'password_confirmation' => 'y'])
            ->assertSessionHasErrors(['nik', 'name', 'phone', 'email', 'password']);
        $this->post('/register', ['nik' => '7371010101900001', 'name' => 'B', 'phone' => '0812345678', 'email' => 'b@example.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertSessionHasErrors('nik');
    }

    public function test_role_separation_and_pending_cannot_rent(): void
    {
        $pending = $this->penyewa(RegistrationStatus::Pending);

        $this->get('/penyewa')->assertRedirect('/login');
        $this->actingAs($pending)->get('/admin')->assertForbidden();
        $this->actingAs($this->admin)->get('/penyewa')->assertForbidden();
        $this->actingAs($pending)->get('/penyewa/rentals/create')->assertRedirect('/penyewa')->assertSessionHas('error');
        $this->actingAs($pending)->post('/penyewa/rentals', $this->payload())->assertRedirect('/penyewa');
        $this->assertSame(0, Rental::count());
        $this->actingAs($pending)->get('/penyewa')->assertOk();
    }

    public function test_daily_rental_with_new_vehicle(): void
    {
        $user = $this->penyewa();

        $this->actingAs($user)->post('/penyewa/rentals', $this->payload())->assertRedirect();

        $rental = Rental::with(['vehicle', 'garage'])->sole();
        $this->assertSame(RentalType::Daily, $rental->rental_type);
        $this->assertSame(today()->toDateString(), $rental->start_date->toDateString());
        $this->assertSame(today()->addDays(2)->toDateString(), $rental->end_date->toDateString()); // inklusif
        $this->assertSame('300000.00', $rental->total_price);
        $this->assertSame('DD 1234 AB', $rental->vehicle->plate_number);
        $this->assertSame(GarageStatus::Used, $rental->garage->status);
        Storage::disk('public')->assertExists($rental->payment_proof_path);
        $this->actingAs($user)->get("/penyewa/rentals/{$rental->id}")->assertOk()->assertSee('C001');
    }

    public function test_future_start_monthly_and_electric_cost(): void
    {
        $user = $this->penyewa();
        $start = today()->addDays(7);

        $this->actingAs($user)->post('/penyewa/rentals', $this->payload([
            'vehicle_type' => 'electric_car', 'plate_number' => 'DD 9 EV', 'brand' => 'Hyundai', 'model' => 'Ioniq 5',
            'battery_capacity_kwh' => 58, 'rental_type' => 'monthly', 'days' => null,
            'start_date' => $start->toDateString(), 'garage_type' => 'electric_car', 'garage_id' => $this->garage('E001')->id,
        ]))->assertRedirect();

        $rental = Rental::sole();
        $this->assertSame(RentalType::Monthly, $rental->rental_type);
        $this->assertSame($start->copy()->addDays($start->daysInMonth - 1)->toDateString(), $rental->end_date->toDateString());
        $this->assertSame(1, $rental->monthly_count);
        $this->assertSame('174000.00', $rental->electricity_cost); // 58 kWh x 3.000
        $this->assertSame(bcadd('3000000.00', '174000.00', 2), $rental->total_price);
    }

    public function test_electric_car_in_regular_garage_pays_no_electricity(): void
    {
        $this->actingAs($this->penyewa())->post('/penyewa/rentals', $this->payload([
            'vehicle_type' => 'electric_car', 'plate_number' => 'DD 8 EV', 'battery_capacity_kwh' => 58,
        ]))->assertRedirect();

        $this->assertSame('0.00', Rental::sole()->electricity_cost);
    }

    public function test_vehicle_garage_compatibility_and_required_fields(): void
    {
        $user = $this->penyewa();

        // motor di garasi mobil
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['vehicle_type' => 'motorcycle']))->assertSessionHasErrors('garage_id');
        // mobil biasa di garasi mobil listrik
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['garage_type' => 'electric_car', 'garage_id' => $this->garage('E001')->id]))->assertSessionHasErrors();
        // plat wajib kecuali sepeda; baterai wajib untuk mobil listrik
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['plate_number' => null]))->assertSessionHasErrors('plate_number');
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['vehicle_type' => 'electric_car']))->assertSessionHasErrors('battery_capacity_kwh');
        $this->assertSame(0, Rental::count());

        // sepeda tanpa plat di garasi sepeda
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload([
            'vehicle_type' => 'bicycle', 'plate_number' => null, 'garage_type' => 'bicycle', 'garage_id' => $this->garage('S001')->id,
        ]))->assertRedirect();
        $this->assertNull(Vehicle::sole()->plate_number);
    }

    public function test_date_and_duration_rules(): void
    {
        $user = $this->penyewa();

        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['start_date' => today()->subDay()->toDateString()]))->assertSessionHasErrors('start_date');
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['days' => 31]))->assertSessionHasErrors('days');
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['days' => 0]))->assertSessionHasErrors('days');
        // di bulan 28 hari, sewa harian maksimal 27 hari (28 hari = bulanan)
        $feb = today()->addYear()->month(2)->startOfMonth();
        while ($feb->daysInMonth !== 28) {
            $feb->addYear()->month(2)->startOfMonth();
        }
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['start_date' => $feb->toDateString(), 'days' => 28]))->assertSessionHasErrors('days');
        $this->assertSame(0, Rental::count());
    }

    public function test_proof_must_be_an_image(): void
    {
        $user = $this->penyewa();

        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['payment_proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('payment_proof');
        $this->actingAs($user)->post('/penyewa/rentals', $this->payload(['payment_proof' => null]))->assertSessionHasErrors('payment_proof');
        $this->assertSame(0, Rental::count());
    }

    public function test_garage_cannot_be_double_booked_but_second_rental_after_first_works(): void
    {
        $a = $this->penyewa();
        $b = $this->penyewa();

        $this->actingAs($a)->post('/penyewa/rentals', $this->payload(['days' => 3]))->assertRedirect();
        // penyewa lain, garasi sama, tanggal tumpang-tindih
        $this->actingAs($b)->post('/penyewa/rentals', $this->payload(['plate_number' => 'DD 2 B', 'start_date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('garage_id');
        // penyewa lain, garasi sama, setelah sewa pertama selesai
        $this->actingAs($b)->post('/penyewa/rentals', $this->payload(['plate_number' => 'DD 2 B', 'start_date' => today()->addDays(3)->toDateString()]))->assertRedirect();

        // kendaraan yang sama tidak boleh punya dua sewa bertumpuk (meski garasi berbeda)
        $vehicle = Vehicle::where('user_id', $a->id)->sole();
        $this->actingAs($a)->post('/penyewa/rentals', [
            'vehicle_id' => $vehicle->id, 'rental_type' => 'daily', 'days' => 2, 'start_date' => today()->addDay()->toDateString(),
            'garage_type' => 'car', 'garage_id' => $this->garage('C002')->id, 'payment_proof' => UploadedFile::fake()->image('b.jpg'),
        ])->assertSessionHasErrors('vehicle_id');
        // sewa kedua: mulai setelah sewa pertama selesai, memakai kendaraan tersimpan
        $this->actingAs($a)->post('/penyewa/rentals', [
            'vehicle_id' => $vehicle->id, 'rental_type' => 'daily', 'days' => 2, 'start_date' => today()->addDays(3)->toDateString(),
            'garage_type' => 'car', 'garage_id' => $this->garage('C002')->id, 'payment_proof' => UploadedFile::fake()->image('b.jpg'),
        ])->assertRedirect();
        $this->assertSame(1, Vehicle::where('user_id', $a->id)->count());
        $this->assertSame(2, Rental::where('user_id', $a->id)->count());
    }

    public function test_quote_endpoint_lists_only_free_garages_and_costs(): void
    {
        $a = $this->penyewa();
        $this->actingAs($a)->post('/penyewa/rentals', $this->payload(['days' => 3]))->assertRedirect();

        $res = $this->actingAs($this->penyewa())->getJson('/penyewa/rentals/quote?'.http_build_query([
            'vehicle_type' => 'car', 'rental_type' => 'daily', 'days' => 2, 'start_date' => today()->toDateString(), 'garage_type' => 'car',
        ]))->assertOk();

        $this->assertSame(today()->addDay()->toDateString(), $res->json('end_date'));
        $this->assertSame(['C002'], array_column($res->json('garages'), 'code'));

        $res = $this->actingAs($this->penyewa())->getJson('/penyewa/rentals/quote?'.http_build_query([
            'vehicle_type' => 'car', 'rental_type' => 'daily', 'days' => 2, 'start_date' => today()->toDateString(),
            'garage_type' => 'car', 'garage_id' => $this->garage('C002')->id,
        ]));
        $this->assertSame('Rp 200.000', $res->json('costs.total'));
    }

    public function test_cannot_view_others_rental_and_pages_render(): void
    {
        $a = $this->penyewa();
        $b = $this->penyewa();
        $this->actingAs($a)->post('/penyewa/rentals', $this->payload())->assertRedirect();
        $rental = Rental::sole();

        $this->actingAs($b)->get("/penyewa/rentals/{$rental->id}")->assertNotFound();
        foreach (['/penyewa', '/penyewa/rentals', '/penyewa/rentals/create', "/penyewa/rentals/{$rental->id}"] as $url) {
            $this->actingAs($a)->get($url)->assertOk();
        }
    }
}
