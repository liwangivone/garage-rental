<?php

namespace Tests\Unit\Models;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\GaragePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class GarageTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday(); // Sabtu, 10 Okt 2026
    }

    public static function codes(): array
    {
        return [
            [GarageType::Bicycle, 1, 'S001'], [GarageType::Car, 10, 'C010'],
            [GarageType::Car, 100, 'C100'], [GarageType::ElectricCar, 999, 'E999'],
        ];
    }

    #[DataProvider('codes')]
    public function test_make_code_pads_to_three_digits(GarageType $type, int $seq, string $expected): void
    {
        $this->assertSame($expected, Garage::makeCode($type, $seq));
    }

    public function test_next_sequence_is_per_type_and_uses_max(): void
    {
        $this->assertSame(1, Garage::nextSequence(GarageType::Car));

        $this->garage(GarageType::Car, 1);
        $this->garage(GarageType::Car, 5);
        $this->garage(GarageType::Bicycle, 1);

        $this->assertSame(6, Garage::nextSequence(GarageType::Car)); // max + 1, bukan jumlah + 1
        $this->assertSame(2, Garage::nextSequence(GarageType::Bicycle));
        $this->assertSame(1, Garage::nextSequence(GarageType::ElectricCar));
    }

    public function test_price_accessors_come_from_type_price(): void
    {
        $garage = $this->garage(GarageType::Car, 1, '80000');
        $other = $this->garage(GarageType::Car, 2, '999999'); // harga tipe sudah ada, tidak menimpa

        $this->assertSame('80000.00', $garage->daily_price);
        $this->assertSame('2000000.00', $garage->monthly_price);
        $this->assertNull($garage->electricity_price_per_kwh);
        $this->assertSame('80000.00', $other->daily_price);

        GaragePrice::where('type', GarageType::Car)->update(['daily_price' => 90000]);
        $this->assertSame('90000.00', $garage->fresh()->daily_price); // berlaku untuk semua garasi tipe itu
    }

    public function test_price_accessors_are_null_without_price(): void
    {
        $garage = $this->garage(GarageType::Car);
        GaragePrice::query()->delete();

        $this->assertNull($garage->daily_price);
        $this->assertNull($garage->monthly_price);
    }

    public static function overlaps(): array
    {
        // Sewa terisi 2026-10-20 s.d. 2026-10-22
        return [
            'sebelum, tidak menyentuh' => ['2026-10-18', '2026-10-19', true],
            'berakhir sehari sebelum mulai' => ['2026-10-19', '2026-10-19', true],
            'berakhir tepat di hari mulai' => ['2026-10-19', '2026-10-20', false],
            'di dalam' => ['2026-10-21', '2026-10-21', false],
            'mulai di hari terakhir' => ['2026-10-22', '2026-10-23', false],
            'sehari setelah selesai' => ['2026-10-23', '2026-10-25', true],
            'membungkus seluruhnya' => ['2026-10-15', '2026-10-30', false],
        ];
    }

    #[DataProvider('overlaps')]
    public function test_availability_boundaries(string $start, string $end, bool $free): void
    {
        $garage = $this->garage();
        $this->rental($garage, $this->vehicle(), '2026-10-20', '2026-10-22');

        $this->assertSame($free, $garage->isFreeBetween(\Carbon\Carbon::parse($start), \Carbon\Carbon::parse($end)));
        $this->assertSame($free, Garage::availableBetween(\Carbon\Carbon::parse($start), \Carbon\Carbon::parse($end))->whereKey($garage->id)->exists());
    }

    public function test_pending_blocks_but_rejected_does_not(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $rejected = $this->rental($garage, $vehicle, '2026-10-20', '2026-10-22', PaymentStatus::Rejected);

        $this->assertTrue($garage->isFreeBetween(now()->setDate(2026, 10, 21), now()->setDate(2026, 10, 21)));

        $rejected->update(['payment_status' => PaymentStatus::Pending]);
        $this->assertFalse($garage->isFreeBetween(now()->setDate(2026, 10, 21), now()->setDate(2026, 10, 21)));
    }

    public function test_free_between_can_ignore_a_rental(): void
    {
        $garage = $this->garage();
        $rental = $this->rental($garage, $this->vehicle(), '2026-10-20', '2026-10-22');

        $this->assertFalse($garage->isFreeBetween(now()->setDate(2026, 10, 20), now()->setDate(2026, 10, 25)));
        $this->assertTrue($garage->isFreeBetween(now()->setDate(2026, 10, 20), now()->setDate(2026, 10, 25), $rental->id));
    }

    public function test_rented_on_and_available_on_scopes(): void
    {
        $busy = $this->garage(GarageType::Car, 1);
        $free = $this->garage(GarageType::Car, 2);
        $this->rental($busy, $this->vehicle(), '2026-10-08', '2026-10-12'); // aktif hari ini
        $this->rental($free, $this->vehicle(), '2026-10-15', '2026-10-17'); // masa depan

        $this->assertSame([$busy->id], Garage::rentedOn()->pluck('id')->all());
        $this->assertSame([$free->id], Garage::notRentedOn()->pluck('id')->all());
        $this->assertSame([$free->id], Garage::availableOn()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$busy->id, $free->id], Garage::availableOn(now()->setDate(2026, 10, 13))->pluck('id')->all());
    }

    public function test_for_vehicle_type_scope(): void
    {
        $this->garage(GarageType::Bicycle, 1);
        $this->garage(GarageType::Car, 1);
        $this->garage(GarageType::ElectricCar, 1);

        $codes = fn (VehicleType $t) => Garage::forVehicleType($t)->orderBy('code')->pluck('code')->all();

        $this->assertSame(['S001'], $codes(VehicleType::Motorcycle));
        $this->assertSame(['C001'], $codes(VehicleType::Car));
        $this->assertSame(['C001', 'E001'], $codes(VehicleType::ElectricCar));
    }

    public function test_current_rental_ignores_rejected_and_other_days(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $this->rental($garage, $vehicle, '2026-10-01', '2026-10-05'); // sudah selesai
        $this->rental($garage, $vehicle, '2026-10-20', '2026-10-22'); // belum mulai
        $this->rental($garage, $vehicle, '2026-10-09', '2026-10-11', PaymentStatus::Rejected);
        $this->assertNull($garage->fresh()->currentRental);

        $active = $this->rental($garage, $vehicle, '2026-10-10', '2026-10-10');
        $this->assertTrue($garage->fresh()->currentRental->is($active));
    }

    public function test_current_rental_found_even_when_a_later_rental_exists(): void
    {
        // Regresi: sewa mendatang tidak boleh menutupi sewa yang sedang berjalan.
        $garage = $this->garage();
        $active = $this->rental($garage, $this->vehicle(), '2026-10-08', '2026-10-12');
        $this->rental($garage, $this->vehicle(), '2026-10-15', '2026-10-20');

        $this->assertTrue($garage->fresh()->currentRental->is($active));
        $this->assertTrue(Garage::with('currentRental')->find($garage->id)->currentRental->is($active));
    }

    public function test_sync_status_transitions(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);

        // sewa mendatang: tetap available
        $future = $this->rental($garage, $vehicle, '2026-10-15', '2026-10-17');
        $garage->refresh()->syncStatus();
        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);

        // hari pertama sewa: used
        $this->travelTo(now()->setDate(2026, 10, 15));
        $garage->refresh()->syncStatus();
        $this->assertSame(GarageStatus::Used, $garage->fresh()->status);

        // hari terakhir masih used, sehari setelahnya available
        $this->travelTo(now()->setDate(2026, 10, 17));
        $garage->refresh()->syncStatus();
        $this->assertSame(GarageStatus::Used, $garage->fresh()->status);
        $this->travelTo(now()->setDate(2026, 10, 18));
        $garage->refresh()->syncStatus();
        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);

        // sewa ditolak melepas garasi
        $this->travelTo(now()->setDate(2026, 10, 16));
        $garage->refresh()->syncStatus();
        $this->assertSame(GarageStatus::Used, $garage->fresh()->status);
        $future->update(['payment_status' => PaymentStatus::Rejected]);
        $garage->refresh()->syncStatus();
        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);
    }

    public function test_sync_all_statuses_updates_in_bulk(): void
    {
        $busy = $this->garage(GarageType::Car, 1);
        $idle = $this->garage(GarageType::Car, 2);
        $this->rental($busy, $this->vehicle(), '2026-10-09', '2026-10-11');
        $idle->update(['status' => GarageStatus::Used]); // status basi

        Garage::syncAllStatuses();

        $this->assertSame(GarageStatus::Used, $busy->fresh()->status);
        $this->assertSame(GarageStatus::Available, $idle->fresh()->status);
    }
}
