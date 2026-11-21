<?php

namespace App\Support;

class Format
{
    public static function rupiah(string|int|float|null $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}
