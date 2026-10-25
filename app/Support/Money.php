<?php

namespace App\Support;

/** Money is calculated in whole minor units (poisha / cents) so 0.1 + 0.2 never goes wrong. */
class Money
{
    public static function toMinor(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromMinor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
