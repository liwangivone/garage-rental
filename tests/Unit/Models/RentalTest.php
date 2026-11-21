<?php

namespace Tests\Unit\Models;

use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Models\Rental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday(); // Sabtu, 10 Okt 2026
    }

    public static function phases(): array
    {
        return [
            'mulai besok' => ['2026-10-11', '2026-10-13', 'upcoming'],
            'mulai hari ini' => ['2026-10-10', '2026-10-12', 'active'],
            'di tengah' => ['2026-10-08', '2026-10-12', 'active'],
            'selesai hari ini' => ['2026-10-08', '2026-10-10', 'active'],
            'selesai kemarin' => ['2026-10-07', '2026-10-09', 'finished'],
            'satu hari hari ini' => ['2026-10-10', '2026-10-10', 'active'],
        ];
    }

    #[DataProvider('phases')]
    public function test_phase(string $start, string $end, string $expected): void
    {
        $rental = $this->rental($this->garage(), $this->vehicle(), $start, $end);

        $this->assertSame($expected, $rental->phase());
        $this->assertSame($expected === 'active', $rental->isActiveOn());
    }

    public function test_payment_scopes_and_flag(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();
        $approved = $this->rental($garage, $vehicle, '2026-10-20', '2026-10-21', PaymentStatus::Approved);
        $pending = $this->rental($garage, $vehicle, '2026-10-22', '2026-10-23', PaymentStatus::Pending);
        $rejected = $this->rental($garage, $vehicle, '2026-10-24', '2026-10-25', PaymentStatus::Rejected);

        $this->assertSame([$approved->id], Rental::approved()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$approved->id, $pending->id], Rental::occupying()->pluck('id')->all());
        $this->assertTrue($approved->isPaymentApproved());
        $this->assertFalse($pending->isPaymentApproved());
        $this->assertFalse($rejected->isPaymentApproved());
    }

    public function test_extendable_error_by_payment_and_end_date(): void
    {
        $garage = $this->garage();
        $vehicle = $this->vehicle();

        $this->assertNull($this->rental($garage, $vehicle, '2026-10-20', '2026-10-21')->extendableError()); // belum mulai
        $this->assertNull($this->rental($garage, $vehicle, '2026-10-08', '2026-10-10', attrs: [])->extendableError()); // selesai hari ini
        $this->assertStringContainsString('berakhir', $this->rental($garage, $vehicle, '2026-10-01', '2026-10-09')->extendableError());
        $this->assertStringContainsString('Verifikasi', $this->rental($garage, $vehicle, '2026-11-01', '2026-11-02', PaymentStatus::Pending)->extendableError());
        $this->assertStringContainsString('Verifikasi', $this->rental($garage, $vehicle, '2026-11-05', '2026-11-06', PaymentStatus::Rejected)->extendableError());
    }

    public function test_extension_error_matrix(): void
    {
        $garage = $this->garage();
        $owner = $this->penyewaUser();
        $vehicle = $this->vehicle($owner);
        $rental = $this->rental($garage, $vehicle, '2026-10-08', '2026-10-12');
        $date = fn (string $d) => \Carbon\Carbon::parse($d);

        // tidak boleh memperpendek atau sama
        $this->assertStringContainsString('setelah tanggal selesai', $rental->extensionError($date('2026-10-12')));
        $this->assertStringContainsString('setelah tanggal selesai', $rental->extensionError($date('2026-10-11')));
        // memperpanjang boleh
        $this->assertNull($rental->extensionError($date('2026-10-13')));
        $this->assertTrue($rental->canExtendTo($date('2026-12-31')));

        // bentrok dengan sewa lain di garasi yang sama
        $this->rental($garage, $this->vehicle(), '2026-10-20', '2026-10-22');
        $this->assertNull($rental->extensionError($date('2026-10-19')));
        $this->assertStringContainsString('garasi ini', $rental->extensionError($date('2026-10-20')));
        $this->assertFalse($rental->canExtendTo($date('2026-10-25')));

        // bentrok dengan sewa lain milik kendaraan yang sama di garasi berbeda
        $this->rental($this->garage(GarageType::Car, 2), $vehicle, '2026-10-14', '2026-10-15');
        $this->assertNull($rental->extensionError($date('2026-10-13')));
        $this->assertStringContainsString('kendaraan yang sama', $rental->extensionError($date('2026-10-14')));
    }

    public function test_rejected_rental_of_others_does_not_block_extension(): void
    {
        $garage = $this->garage();
        $rental = $this->rental($garage, $this->vehicle(), '2026-10-08', '2026-10-12');
        $this->rental($garage, $this->vehicle(), '2026-10-14', '2026-10-16', PaymentStatus::Rejected);

        $this->assertNull($rental->extensionError(\Carbon\Carbon::parse('2026-10-20')));
    }

    public function test_casts(): void
    {
        $rental = $this->rental($this->garage(), $this->vehicle(), '2026-10-10', '2026-10-12')->fresh();

        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $rental->start_date);
        $this->assertSame(PaymentStatus::Approved, $rental->payment_status);
        $this->assertSame('300000.00', $rental->total_price);
        $this->assertSame(3, $rental->duration_days);
    }
}
