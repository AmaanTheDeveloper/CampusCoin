<?php

namespace App\Support;

/**
 * Decimal-safe string arithmetic for financial aggregates.
 * Falls back to rounded float math only if BCMath is unavailable.
 */
class MoneyMath
{
    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_string($value) && is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return '0.00';
    }

    public static function add(string $left, string $right): string
    {
        $left = self::normalize($left);
        $right = self::normalize($right);

        if (function_exists('bcadd')) {
            return self::normalize(bcadd($left, $right, 4));
        }

        return self::normalize((float) $left + (float) $right);
    }

    public static function sub(string $left, string $right): string
    {
        $left = self::normalize($left);
        $right = self::normalize($right);

        if (function_exists('bcsub')) {
            return self::normalize(bcsub($left, $right, 4));
        }

        return self::normalize((float) $left - (float) $right);
    }

    public static function percentOf(string $part, string $whole): ?float
    {
        $part = self::normalize($part);
        $whole = self::normalize($whole);

        if (self::compare($whole, '0.00') === 0) {
            return null;
        }

        if (function_exists('bcdiv') && function_exists('bcmul')) {
            $ratio = bcdiv($part, $whole, 8);
            $percent = bcmul($ratio, '100', 4);

            return round((float) $percent, 2);
        }

        return round(((float) $part / (float) $whole) * 100, 2);
    }

    /**
     * @return int -1|0|1
     */
    public static function compare(string $left, string $right): int
    {
        $left = self::normalize($left);
        $right = self::normalize($right);

        if (function_exists('bccomp')) {
            return bccomp($left, $right, 2);
        }

        return (float) $left <=> (float) $right;
    }
}
