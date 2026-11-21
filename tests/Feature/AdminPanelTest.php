<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'registration_status' => RegistrationStatus::Approved]);
    }

    public function test_guests_are_redirected_and_penyewa_is_forbidden(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_login_redirects_by_role(): void
    {
        $admin = $this->admin();
        $penyewa = User::factory()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->post('/logout');
        $this->post('/login', ['email' => $penyewa->email, 'password' => 'password'])->assertRedirect('/penyewa');
        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'salah'])->assertSessionHasErrors('email');
    }

    public function test_all_admin_pages_render_with_demo_data(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('role', UserRole::Admin)->first();
        $rental = Rental::whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->first();

        foreach ([
            '/admin', '/admin/garages', '/admin/garages?type=car', '/admin/garages/create',
            '/admin/registrations', '/admin/registrations?status=rejected', '/admin/extensions',
            "/admin/extensions/{$rental->id}", "/admin/extensions/{$rental->id}?new_end_date=".today()->addDays(40)->toDateString(),
            '/admin/reports', '/admin/reports?view=daily', '/admin/reports?view=type&year='.now()->year,
            '/admin/reports?view=penyewa&month='.now()->month,
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_batch_creation_generates_sequential_codes_and_enforces_999(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/garages', ['type' => 'bicycle', 'quantity' => 3, 'daily_price' => 10000])
            ->assertRedirect();
        $this->actingAs($admin)->post('/admin/garages', ['type' => 'bicycle', 'quantity' => 2, 'daily_price' => 12000]);
        $this->assertSame(['S001', 'S002', 'S003', 'S004', 'S005'], Garage::where('type', GarageType::Bicycle)->orderBy('sequence')->pluck('code')->all());

        // listrik wajib untuk mobil listrik
        $this->actingAs($admin)->post('/admin/garages', ['type' => 'electric_car', 'quantity' => 1, 'daily_price' => 50000])
            ->assertSessionHasErrors('electricity_price_per_kwh');
        $this->actingAs($admin)->post('/admin/garages', ['type' => 'electric_car', 'quantity' => 2, 'daily_price' => 50000, 'electricity_price_per_kwh' => 3000]);
        $this->assertSame(['E001', 'E002'], Garage::where('type', GarageType::ElectricCar)->orderBy('sequence')->pluck('code')->all());

        // melebihi 999 per tipe ditolak, tidak ada garasi baru
        $this->actingAs($admin)->post('/admin/garages', ['type' => 'bicycle', 'quantity' => 999, 'daily_price' => 10000])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(5, Garage::where('type', GarageType::Bicycle)->count());
        $this->actingAs($admin)->post('/admin/garages', ['type' => 'bicycle', 'quantity' => 1000, 'daily_price' => 10000])
            ->assertSessionHasErrors('quantity');
    }

    public function test_approve_and_reject_registration(): void
    {
        $admin = $this->admin();
        [$a, $b] = [User::factory()->create(), User::factory()->create()];

        $this->actingAs($admin)->post("/admin/registrations/{$a->id}/approve")->assertSessionHas('status');
        $this->actingAs($admin)->post("/admin/registrations/{$b->id}/reject", ['rejection_reason' => 'KTP buram'])->assertSessionHas('status');

        $this->assertSame(RegistrationStatus::Approved, $a->fresh()->registration_status);
        $this->assertSame($admin->id, $a->fresh()->verified_by);
        $this->assertSame(RegistrationStatus::Rejected, $b->fresh()->registration_status);
        $this->assertSame('KTP buram', $b->fresh()->rejection_reason);

        // sudah diproses: tidak bisa diubah lagi
        $this->actingAs($admin)->post("/admin/registrations/{$a->id}/reject")->assertSessionHas('error');
        $this->assertSame(RegistrationStatus::Approved, $a->fresh()->registration_status);
        // admin tidak bisa "diverifikasi"
        $this->actingAs($admin)->post("/admin/registrations/{$admin->id}/approve")->assertNotFound();
    }

    public function test_extension_flow_charges_new_rule_and_stores_history(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
        $admin = User::where('role', UserRole::Admin)->first();
        // Sewa Avanza di C001: 8 hari harian @75.000 = 600.000
        $rental = Rental::whereHas('garage', fn ($q) => $q->where('code', 'C001'))->whereDate('end_date', '>=', today())->firstOrFail();
        $this->assertSame('600000.00', $rental->total_price);

        // Perpanjang jadi 40 hari: bulan pertama (mulai bulan itu) + sisa harian
        $newEnd = $rental->start_date->copy()->addDays(39);
        $expected = \App\Services\RentalPeriodCalculator::rentalCost($rental->start_date, $newEnd, '75000');

        $this->actingAs($admin)->post("/admin/extensions/{$rental->id}", [
            'new_end_date' => $newEnd->toDateString(),
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect('/admin/extensions');

        $rental->refresh();
        $this->assertSame($newEnd->toDateString(), $rental->end_date->toDateString());
        $this->assertSame($expected, $rental->total_price);
        $this->assertSame(1, $rental->monthly_count);
        $extension = $rental->extensions()->sole();
        $this->assertSame(bcsub($expected, '600000.00', 2), $extension->additional_cost);
        Storage::disk('public')->assertExists($extension->payment_proof_path);
    }

    public function test_extension_is_rejected_when_not_later_or_overlapping_other_rental(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
        $admin = User::where('role', UserRole::Admin)->first();
        $rental = Rental::whereHas('garage', fn ($q) => $q->where('code', 'C001'))->whereDate('end_date', '>=', today())->firstOrFail();
        $proof = fn () => UploadedFile::fake()->image('b.jpg');

        // tidak lebih akhir dari tanggal selesai saat ini
        $this->actingAs($admin)->post("/admin/extensions/{$rental->id}", ['new_end_date' => $rental->end_date->toDateString(), 'payment_proof' => $proof()])
            ->assertSessionHasErrors('new_end_date');

        // bentrok dengan sewa lain di garasi yang sama
        $other = $rental->replicate()->fill([
            'vehicle_id' => \App\Models\Vehicle::where('plate_number', 'DD 5678 CD')->value('id'),
            'start_date' => $rental->end_date->copy()->addDays(5), 'end_date' => $rental->end_date->copy()->addDays(8),
        ]);
        $other->save();
        $this->actingAs($admin)->post("/admin/extensions/{$rental->id}", ['new_end_date' => $rental->end_date->copy()->addDays(6)->toDateString(), 'payment_proof' => $proof()])
            ->assertSessionHasErrors('new_end_date');
        $this->assertSame(0, $rental->extensions()->count());

        // preview menampilkan pesan error, bukan rincian
        $this->actingAs($admin)->get("/admin/extensions/{$rental->id}?new_end_date=".$rental->end_date->copy()->addDays(6)->toDateString())
            ->assertOk()->assertSee('bentrok dengan sewa lain');

        // sewa yang sudah berakhir / belum mulai tidak bisa diperpanjang
        $this->actingAs($admin)->get("/admin/extensions/{$other->id}")->assertNotFound();
    }

    public function test_report_numbers(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('role', UserRole::Admin)->first();
        $year = now()->year;
        $expected = Rental::whereYear('start_date', $year)->sum('total_price');

        $this->actingAs($admin)->get("/admin/reports?view=monthly&year={$year}")
            ->assertOk()->assertSee(\App\Support\Format::rupiah($expected));
    }
}
