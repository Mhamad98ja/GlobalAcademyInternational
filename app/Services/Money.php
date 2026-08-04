<?php

declare(strict_types=1);

namespace App\Services;

/** Integer-cent money arithmetic; no float calculations are used in workflows. */
final class Money
{
    public static function cents(string|int|float|null $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Invalid monetary amount.');
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
