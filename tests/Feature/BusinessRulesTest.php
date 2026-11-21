<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Garage;
use App\Models\GaragePrice;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageBatchService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'registration_status' => RegistrationStatus::Approved]);
        app(GarageBatchService::class)->create($this->admin, GarageType::Car, 3, '100000', null);
        app(GarageBatchService::class)->create($this->admin, GarageType::ElectricCar, 1, '120000', '3000');
    }

    private function penyewa(RegistrationStatus $s = RegistrationStatus::Approved): User
    {
        return User::factory()->create(['registration_status' => $s]);
    }

    private function book(User $user, array $o = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post('/penyewa/rentals', array_merge([
            'vehicle_type' => 'car', 'plate_number' => 'DD 1 A', 'brand' => 'Toyota', 'model' => 'Avanza',
            'rental_type' => 'daily', 'days' => 3, 'start_date' => today()->toDateString(),
            'garage_type' => 'car', 'garage_id' => Garage::where('code', 'C001')->value('id'),
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ], $o));
    }

    public function test_timezone_is_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_garage_status_follows_rental_dates(): void
    {
        $user = $this->penyewa();
        $start = today()->addDays(5);
        $this->book($user, ['start_date' => $start->toDateString(), 'days' => 2])->assertRedirect();
        $garage = Garage::where('code', 'C001')->first();

        // belum mulai: masih available
        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);

        // pada tanggal mulai: used
        $this->travelTo($start->copy()->setTime(9, 0));
        $this->artisan('garages:sync-status')->assertSuccessful();
        $this->assertSame(GarageStatus::Used, $garage->fresh()->status);

        // setelah tanggal selesai: kembali available
        $this->travelTo($start->copy()->addDays(2)->setTime(9, 0));
        $this->artisan('garages:sync-status')->assertSuccessful();
        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);
    }

    public function test_booking_starting_today_marks_garage_used_immediately(): void
    {
        $this->book($this->penyewa())->assertRedirect();

        $this->assertSame(GarageStatus::Used, Garage::where('code', 'C001')->first()->status);
        $this->assertSame(PaymentStatus::Pending, Rental::sole()->fresh()->payment_status);
    }

    public function test_pending_rental_holds_garage_and_rejection_releases_it(): void
    {
        $user = $this->penyewa();
        $this->book($user)->assertRedirect();
        $rental = Rental::sole();
        $garage = Garage::where('code', 'C001')->first();

        // belum diverifikasi: tetap menahan garasi
        $this->assertFalse($garage->isFreeBetween(today(), today()->addDays(2)));

        $this->actingAs($this->admin)->post("/admin/rentals/{$rental->id}/reject", ['rejection_reason' => 'Nominal salah'])->assertSessionHas('status');
        $rental->refresh();
        $this->assertSame(PaymentStatus::Rejected, $rental->payment_status);
        $this->assertSame('Nominal salah', $rental->payment_rejection_reason);
        $this->assertSame($this->admin->id, $rental->payment_verified_by);

        // dilepas: bisa dipesan lagi, status kembali available
        $this->assertTrue($garage->isFreeBetween(today(), today()->addDays(2)));
        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);
        $this->actingAs($user)->get("/penyewa/rentals/{$rental->id}")->assertOk()->assertSee('Pembayaran ditolak')->assertSee('Nominal salah');
        $this->book($this->penyewa(), ['plate_number' => 'DD 2 B'])->assertRedirect();
    }

    public function test_approve_payment_and_cannot_decide_twice(): void
    {
        $this->book($this->penyewa())->assertRedirect();
        $rental = Rental::sole();

        $this->actingAs($this->admin)->post("/admin/rentals/{$rental->id}/approve")->assertSessionHas('status');
        $this->assertSame(PaymentStatus::Approved, $rental->fresh()->payment_status);
        $this->actingAs($this->admin)->post("/admin/rentals/{$rental->id}/reject")->assertSessionHas('error');
        $this->assertSame(PaymentStatus::Approved, $rental->fresh()->payment_status);

        // penyewa tidak bisa memverifikasi
        $this->actingAs($this->penyewa())->post("/admin/rentals/{$rental->id}/approve")->assertForbidden();
    }

    public function test_reports_count_only_approved_and_split_electricity(): void
    {
        $user = $this->penyewa();
        $this->book($user, ['days' => 2])->assertRedirect(); // C001: 200.000
        $this->book($user, [
            'vehicle_type' => 'electric_car', 'plate_number' => 'DD 9 EV', 'battery_capacity_kwh' => 50, 'battery_current_kwh' => 20, 'days' => 1,
            'garage_type' => 'electric_car', 'garage_id' => Garage::where('code', 'E001')->value('id'),
        ])->assertRedirect(); // E001: 120.000 + 90.000 listrik ((50 - 20) kWh x 3.000)
        $this->book($user, ['plate_number' => 'DD 3 C', 'garage_id' => Garage::where('code', 'C002')->value('id')])->assertRedirect();
        [$car, $ev, $rejected] = Rental::orderBy('id')->get()->all();

        $report = fn () => app(\App\Services\RentalReportService::class)->build('type', now()->year, null);

        // belum ada yang terverifikasi
        $this->assertSame(0, $report()['count']);

        $this->actingAs($this->admin)->post("/admin/rentals/{$car->id}/approve");
        $this->actingAs($this->admin)->post("/admin/rentals/{$ev->id}/approve");
        $this->actingAs($this->admin)->post("/admin/rentals/{$rejected->id}/reject");

        $r = $report();
        $this->assertSame(2, $r['count']);
        $this->assertSame(320000.0, $r['rental']);
        $this->assertSame(90000.0, $r['electricity']);
        $this->assertSame(410000.0, $r['revenue']);
        $this->actingAs($this->admin)->get('/admin/reports?view=type')->assertOk()->assertSee('Listrik');
    }

    public function test_admin_can_delete_rental_and_garage_is_released(): void
    {
        $this->book($this->penyewa())->assertRedirect();
        $rental = Rental::sole();
        $garage = Garage::where('code', 'C001')->first();
        $path = $rental->payment_proof_path;
        Storage::disk('public')->assertExists($path);
        $this->assertSame(GarageStatus::Used, $garage->fresh()->status);

        $this->actingAs($this->penyewa())->delete("/admin/rentals/{$rental->id}")->assertForbidden();
        $this->assertSame(1, Rental::count());

        $this->actingAs($this->admin)->delete("/admin/rentals/{$rental->id}")->assertRedirect('/admin/rentals');

        $this->assertSame(0, Rental::count());
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);
    }

    public function test_start_date_limited_to_30_days_ahead(): void
    {
        $user = $this->penyewa();

        $this->book($user, ['start_date' => today()->addDays(31)->toDateString()])->assertSessionHasErrors('start_date');
        $this->book($user, ['start_date' => today()->addDays(30)->toDateString()])->assertRedirect();
        $this->assertSame(1, Rental::count());
    }

    public function test_one_price_per_garage_type_and_old_rentals_keep_snapshot(): void
    {
        $this->assertSame('100000.00', GaragePrice::forType(GarageType::Car)->daily_price);
        $this->book($this->penyewa(), ['days' => 1])->assertRedirect();
        $old = Rental::sole();

        // batch baru dengan harga berbeda memperbarui harga tipe
        $this->actingAs($this->admin)->post('/admin/garages', ['type' => 'car', 'quantity' => 1, 'daily_price' => 150000])->assertRedirect();
        $this->assertSame(1, GaragePrice::where('type', GarageType::Car)->count());
        $this->assertSame('150000.00', GaragePrice::forType(GarageType::Car)->daily_price);
        // semua garasi tipe itu memakai harga baru
        $this->assertSame('150000.00', Garage::where('code', 'C001')->first()->daily_price);
        $this->assertSame('3750000.00', Garage::where('code', 'C004')->first()->monthly_price);

        // sewa lama tidak berubah; sewa baru memakai harga baru
        $this->assertSame('100000.00', $old->fresh()->total_price);
        $this->book($this->penyewa(), ['plate_number' => 'DD 4 D', 'garage_id' => Garage::where('code', 'C002')->value('id'), 'days' => 1])->assertRedirect();
        $this->assertSame('150000.00', Rental::orderByDesc('id')->first()->total_price);

        // halaman garasi menampilkan harga per tipe
        $this->actingAs($this->admin)->get('/admin/garages')->assertOk()->assertSee('Rp 150.000');
    }

    public function test_plate_number_is_not_unique(): void
    {
        $a = $this->penyewa();
        $b = $this->penyewa();

        $this->book($a, ['plate_number' => 'DD 1 A'])->assertRedirect();
        $this->book($b, ['plate_number' => 'DD 1 A', 'garage_id' => Garage::where('code', 'C002')->value('id')])->assertRedirect();

        $this->assertSame(2, Vehicle::where('plate_number', 'DD 1 A')->count());
    }

    public function test_rejected_registrant_can_fix_data_and_resubmit(): void
    {
        $user = $this->penyewa(RegistrationStatus::Rejected);
        $user->update(['rejection_reason' => 'NIK salah', 'nik' => '1111111111111111', 'verified_by' => $this->admin->id, 'verified_at' => now()]);

        $this->actingAs($user)->get('/penyewa/profile')->assertOk()->assertSee('NIK salah');
        $this->actingAs($user)->put('/penyewa/profile', ['nik' => '123', 'name' => 'Baru', 'phone' => '081234567890'])->assertSessionHasErrors('nik');
        // NIK sendiri tidak dianggap duplikat; NIK orang lain ditolak
        $other = $this->penyewa();
        $this->actingAs($user)->put('/penyewa/profile', ['nik' => $other->nik, 'name' => 'Baru', 'phone' => '081234567890'])->assertSessionHasErrors('nik');
        $this->actingAs($user)->put('/penyewa/profile', ['nik' => '1111111111111111', 'name' => 'Nama Baru', 'phone' => '081234567890'])->assertRedirect('/penyewa');

        $user->refresh();
        $this->assertSame(RegistrationStatus::Pending, $user->registration_status);
        $this->assertNull($user->rejection_reason);
        $this->assertNull($user->verified_by);
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame(1, User::pendingVerification()->where('id', $user->id)->count());

        // yang bukan berstatus ditolak tidak bisa memakai halaman ini
        $this->actingAs($user)->get('/penyewa/profile')->assertRedirect('/penyewa');
        $this->actingAs($user)->put('/penyewa/profile', ['nik' => '1111111111111111', 'name' => 'X', 'phone' => '081234567890'])->assertForbidden();
        $this->actingAs($user)->get('/penyewa')->assertOk()->assertSee('Menunggu verifikasi');
    }

    public function test_password_reset_via_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'andi@example.test']);

        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'andi@example.test'])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'tidak.ada@example.test'])->assertSessionHasErrors('email');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=andi@example.test")->assertOk();
        $this->post('/reset-password', ['token' => 'salah', 'email' => 'andi@example.test', 'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1'])->assertSessionHasErrors('email');
        $this->post('/reset-password', ['token' => $token, 'email' => 'andi@example.test', 'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1'])->assertRedirect('/login');

        $this->post('/login', ['email' => 'andi@example.test', 'password' => 'passwordbaru1'])->assertRedirect('/penyewa');
    }
}
