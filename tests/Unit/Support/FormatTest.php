<?php

namespace Tests\Unit\Support;

use App\Support\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public static function values(): array
    {
        return [
            'nol' => [0, 'Rp 0'],
            'null dianggap nol' => [null, 'Rp 0'],
            'ribuan' => [75000, 'Rp 75.000'],
            'jutaan' => [1500000, 'Rp 1.500.000'],
            'string desimal dari database' => ['75000.00', 'Rp 75.000'],
            'dibulatkan ke rupiah' => [1234.56, 'Rp 1.235'],
            'string angka' => ['2500000', 'Rp 2.500.000'],
            'negatif' => [-500000, 'Rp -500.000'],
        ];
    }

    #[DataProvider('values')]
    public function test_rupiah(string|int|float|null $input, string $expected): void
    {
        $this->assertSame($expected, Format::rupiah($input));
    }
}
