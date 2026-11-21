<?php

namespace Tests\Unit\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\GaragePrice;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RentalBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class RentalBookingServiceTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private RentalBookingService $service;
    private User $user;
    private Garage $car;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->freezeToday(); // Sabtu, 10 Okt 2026 (Oktober = 31 hari)
        $this->service = app(RentalBookingService::class);
        $this->user = $this->penyewaUser();
        $this->car = $this->garage(GarageType::Car, 1, '100000');
    }

    private function input(array $o = []): array
    {
        return array_merge([
            'vehicle_type' => 'car', 'rental_type' => 'daily', 'days' => 3, 'start_date' => '2026-10-10',
            'garage_type' => 'car', 'garage_id' => $this->car->id,
        ], $o);
    }

    private function quote(array $o = []): array
    {
        return $this->service->quote($this->user, $this->input($o));
    }

    public static function maxDaily(): array
    {
        return [
            'Okt 31 hari' => ['2026-10-01', 30], 'Apr 30 hari' => ['2026-04-01', 29],
            'Feb 2027 28 hari' => ['2027-02-01', 27], 'Feb 2028 kabisat' => ['2028-02-01', 28],
        ];
    }

    #[DataProvider('maxDaily')]
    public function test_max_daily_days(string $start, int $expected): void
    {
        $this->assertSame($expected, RentalBookingService::maxDailyDays(Carbon::parse($start)));
    }

    public function test_valid_daily_quote(): void
    {
        $q = $this->quote();

        $this->assertSame([], $q['errors']);
        $this->assertSame('2026-10-12', $q['end']->toDateString()); // inklusif
        $this->assertSame(3, $q['duration_days']);
        $this->assertSame(['monthly' => 0, 'daily' => 3], $q['breakdown']);
        $this->assertSame('300000.00', $q['costs']['total']);
        $this->assertSame('0.00', $q['costs']['electricity_cost']);
        $this->assertSame(30, $q['max_daily_days']);
    }

    public function test_valid_monthly_quote(): void
    {
        $q = $this->quote(['rental_type' => 'monthly', 'days' => null, 'start_date' => '2026-10-15']);

        $this->assertSame([], $q['errors']);
        $this->assertSame('2026-11-14', $q['end']->toDateString()); // 31 hari inklusif
        $this->assertSame(['monthly' => 1, 'daily' => 0], $q['breakdown']);
        $this->assertSame('2500000.00', $q['costs']['total']);
    }

    public function test_start_date_cannot_be_in_the_past(): void
    {
        $q = $this->quote(['start_date' => '2026-10-09']);

        $this->assertArrayHasKey('start_date', $q['errors']);
        $this->assertNull($q['costs']);
        $this->assertNull($q['end']);
    }

    public function test_start_date_limited_to_30_days_ahead(): void
    {
        $this->assertSame([], $this->quote(['start_date' => '2026-11-09'])['errors']); // +30
        $this->assertArrayHasKey('start_date', $this->quote(['start_date' => '2026-11-10'])['errors']); // +31
    }

    public function test_daily_days_bounds(): void
    {
        $this->assertArrayHasKey('days', $this->quote(['days' => 0])['errors']);
        $this->assertArrayHasKey('days', $this->quote(['days' => 31])['errors']);
        $this->assertSame([], $this->quote(['days' => 1])['errors']);
        $this->assertSame([], $this->quote(['days' => 30])['errors']);
    }

    public function test_daily_days_bound_shrinks_in_short_month(): void
    {
        $this->travelTo(Carbon::parse('2027-02-01 09:00')); // Feb 2027 = 28 hari

        $this->assertArrayHasKey('days', $this->quote(['start_date' => '2027-02-01', 'days' => 28])['errors']);
        $this->assertSame([], $this->quote(['start_date' => '2027-02-01', 'days' => 27])['errors']);
        $this->assertSame(27, $this->quote(['start_date' => '2027-02-01', 'days' => 27])['max_daily_days']);
    }

    public function test_missing_inputs_do_not_crash(): void
    {
        $q = $this->service->quote($this->user, []);

        $this->assertNull($q['end']);
        $this->assertNull($q['costs']);
        $this->assertSame([], $q['garages']);

        $q = $this->service->quote($this->user, ['start_date' => 'bukan-tanggal']);
        $this->assertArrayHasKey('start_date', $q['errors']);
    }

    public function test_incompatible_garage_type_is_rejected(): void
    {
        $electric = $this->garage(GarageType::ElectricCar, 1);

        $q = $this->quote(['garage_type' => 'electric_car', 'garage_id' => $electric->id]);

        $this->assertArrayHasKey('garage_type', $q['errors']);
        $this->assertArrayHasKey('garage_id', $q['errors']);
        $this->assertNull($q['costs']);
        $this->assertSame([], $q['garages']);
    }

    public function test_motorcycle_cannot_take_car_garage(): void
    {
        $q = $this->quote(['vehicle_type' => 'motorcycle']);

        $this->assertArrayHasKey('garage_id', $q['errors']);
    }

    public function test_garage_list_excludes_occupied_but_not_rejected(): void
    {
        $other = $this->garage(GarageType::Car, 2);
        $this->rental($this->car, $this->vehicle(), '2026-10-10', '2026-10-12');
        $this->rental($other, $this->vehicle(), '2026-10-10', '2026-10-12', PaymentStatus::Rejected);

        $q = $this->quote(['garage_id' => null]);

        $this->assertSame(['C002'], $q['garages']->pluck('code')->all());
    }

    public function test_occupied_garage_is_rejected(): void
    {
        $this->rental($this->car, $this->vehicle(), '2026-10-11', '2026-10-11');

        $q = $this->quote(); // 10-12 Okt bentrok di 11 Okt

        $this->assertArrayHasKey('garage_id', $q['errors']);
        $this->assertNull($q['costs']);
    }

    public function test_garage_list_empty_without_garage_type(): void
    {
        $this->assertSame([], $this->quote(['garage_type' => null, 'garage_id' => null])['garages']);
    }

    public function test_unknown_garage_is_rejected(): void
    {
        $this->assertArrayHasKey('garage_id', $this->quote(['garage_id' => 99999])['errors']);
    }

    public function test_cannot_use_someone_elses_vehicle(): void
    {
        $theirs = $this->vehicle($this->penyewaUser());

        $q = $this->quote(['vehicle_id' => $theirs->id, 'vehicle_type' => null]);

        $this->assertArrayHasKey('vehicle_id', $q['errors']);
    }

    public function test_vehicle_overlap_is_rejected_but_back_to_back_is_fine(): void
    {
        $vehicle = $this->vehicle($this->user);
        $this->rental($this->garage(GarageType::Car, 2), $vehicle, '2026-10-10', '2026-10-12');

        $overlap = $this->quote(['vehicle_id' => $vehicle->id, 'vehicle_type' => null, 'start_date' => '2026-10-12', 'days' => 2]);
        $this->assertArrayHasKey('vehicle_id', $overlap['errors']);

        $next = $this->quote(['vehicle_id' => $vehicle->id, 'vehicle_type' => null, 'start_date' => '2026-10-13', 'days' => 2]);
        $this->assertSame([], $next['errors']);
    }

    public function test_electricity_cost_only_for_electric_car_in_electric_garage(): void
    {
        $electric = $this->garage(GarageType::ElectricCar, 1, '120000', '3000');

        // mobil listrik di garasi listrik: 57,5 kWh x 3.000 = 172.500, sekali bayar
        $ev = $this->quote(['vehicle_type' => 'electric_car', 'battery_capacity_kwh' => 57.5, 'garage_type' => 'electric_car', 'garage_id' => $electric->id, 'days' => 2]);
        $this->assertSame('172500.00', $ev['costs']['electricity_cost']);
        $this->assertSame('240000.00', $ev['costs']['rental_cost']);
        $this->assertSame('412500.00', $ev['costs']['total']);

        // mobil listrik di garasi mobil biasa: tanpa listrik
        $inCar = $this->quote(['vehicle_type' => 'electric_car', 'battery_capacity_kwh' => 57.5]);
        $this->assertSame('0.00', $inCar['costs']['electricity_cost']);

        // tanpa kapasitas baterai: tidak ada biaya listrik
        $noBattery = $this->quote(['vehicle_type' => 'electric_car', 'garage_type' => 'electric_car', 'garage_id' => $electric->id]);
        $this->assertSame('0.00', $noBattery['costs']['electricity_cost']);
    }

    public function test_saved_electric_vehicle_uses_its_own_battery(): void
    {
        $electric = $this->garage(GarageType::ElectricCar, 1, '120000', '3000');
        $ev = $this->vehicle($this->user, VehicleType::ElectricCar, ['battery_capacity_kwh' => 40]);

        $q = $this->quote(['vehicle_id' => $ev->id, 'vehicle_type' => null, 'garage_type' => 'electric_car', 'garage_id' => $electric->id, 'days' => 1]);

        $this->assertSame('120000.00', $q['costs']['electricity_cost']); // 40 x 3.000
    }

    public function test_quote_for_json_shape(): void
    {
        $json = $this->service->quoteForJson($this->user, $this->input());

        $this->assertSame([], $json['errors']);
        $this->assertSame('2026-10-12', $json['end_date']);
        $this->assertSame(3, $json['duration_days']);
        $this->assertSame('Rp 300.000', $json['costs']['total']);
        $this->assertFalse($json['costs']['has_electricity']);
        $this->assertSame(['car'], $json['allowed_garage_types']);
        $this->assertSame('C001', $json['garages'][0]['code']);
        $this->assertSame('Rp 100.000', $json['garages'][0]['daily_price']);
        $this->assertSame('Rp 2.500.000', $json['garages'][0]['monthly_price']);
    }

    private function book(array $o = [], ?array $vehicleData = null): Rental
    {
        return $this->service->book(
            $this->user,
            $this->input($o),
            $vehicleData ?? ['type' => VehicleType::Car, 'plate_number' => 'DD 1 AB', 'brand' => 'Toyota', 'model' => 'Avanza', 'battery_capacity_kwh' => null],
            UploadedFile::fake()->image('bukti.jpg'),
        );
    }

    public function test_book_creates_pending_rental_with_snapshot_and_vehicle(): void
    {
        $rental = $this->book();

        $this->assertSame(PaymentStatus::Pending, $rental->fresh()->payment_status);
        $this->assertSame(RentalType::Daily, $rental->rental_type);
        $this->assertSame('300000.00', $rental->total_price);
        $this->assertSame('100000.00', $rental->daily_price);
        $this->assertSame('2500000.00', $rental->monthly_price);
        $this->assertSame(3, $rental->daily_count);
        $this->assertSame($this->user->id, $rental->user_id);
        $this->assertSame('DD 1 AB', $rental->vehicle->plate_number);
        $this->assertSame($this->user->id, $rental->vehicle->user_id);
        Storage::disk('public')->assertExists($rental->payment_proof_path);

        // harga berikutnya berubah, sewa ini tetap
        GaragePrice::where('type', GarageType::Car)->update(['daily_price' => 999999]);
        $this->assertSame('100000.00', $rental->fresh()->daily_price);
    }

    public function test_book_monthly_records_one_monthly_block(): void
    {
        $rental = $this->book(['rental_type' => 'monthly', 'days' => null, 'start_date' => '2026-10-20']);

        $this->assertSame(RentalType::Monthly, $rental->rental_type);
        $this->assertSame(1, $rental->monthly_count);
        $this->assertSame(0, $rental->daily_count);
        $this->assertSame('2500000.00', $rental->total_price);
        $this->assertSame('2026-11-19', $rental->end_date->toDateString());
    }

    public function test_book_reuses_saved_vehicle(): void
    {
        $vehicle = $this->vehicle($this->user);

        $rental = $this->book(['vehicle_id' => $vehicle->id, 'vehicle_type' => null], []);

        $this->assertSame($vehicle->id, $rental->vehicle_id);
        $this->assertSame(1, Vehicle::count());
    }

    public function test_garage_status_depends_on_start_date(): void
    {
        $today = $this->book(['start_date' => '2026-10-10']);
        $later = $this->book(['start_date' => '2026-10-20', 'garage_id' => $this->garage(GarageType::Car, 2)->id], ['type' => VehicleType::Car, 'plate_number' => 'DD 2 AB', 'brand' => 'B', 'model' => 'M', 'battery_capacity_kwh' => null]);

        $this->assertSame(GarageStatus::Used, $today->garage->fresh()->status);
        $this->assertSame(GarageStatus::Available, $later->garage->fresh()->status);
    }

    public function test_invalid_booking_is_atomic(): void
    {
        $this->book();

        try {
            $this->book(['start_date' => '2026-10-11'], ['type' => VehicleType::Car, 'plate_number' => 'DD 9 ZZ', 'brand' => 'B', 'model' => 'M', 'battery_capacity_kwh' => null]);
            $this->fail('Seharusnya melempar ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('garage_id', $e->errors());
        }

        $this->assertSame(1, Rental::count());
        $this->assertSame(1, Vehicle::count()); // kendaraan baru tidak ikut tersimpan
        $this->assertNull(Vehicle::where('plate_number', 'DD 9 ZZ')->first());
    }

    public function test_booking_requires_a_garage(): void
    {
        try {
            $this->book(['garage_id' => null]);
            $this->fail('Seharusnya melempar ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('Pilih garasi yang tersedia.', $e->errors()['garage_id'][0]);
        }

        $this->assertSame(0, Rental::count());
    }

    public function test_booking_fails_when_type_has_no_price(): void
    {
        GaragePrice::query()->delete();

        $q = $this->quote();

        $this->assertArrayHasKey('garage_id', $q['errors']);
        $this->assertNull($q['costs']);
    }
}
