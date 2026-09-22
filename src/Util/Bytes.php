<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Util;

/**
 * Converts the raw byte counts the bandwidth endpoints return.
 *
 * Vultr reports bandwidth in binary units, so 1 GB here means 1024^3 bytes.
 */
final class Bytes
{
    private const KIBIBYTE = 1024;
    private const MEBIBYTE = 1024 ** 2;
    private const GIBIBYTE = 1024 ** 3;
    private const TEBIBYTE = 1024 ** 4;

    private function __construct()
    {
    }

    public static function toKilobytes(int $bytes, int $decimals = 2): float
    {
        return round($bytes / self::KIBIBYTE, $decimals);
    }

    public static function toMegabytes(int $bytes, int $decimals = 2): float
    {
        return round($bytes / self::MEBIBYTE, $decimals);
    }

    public static function toGigabytes(int $bytes, int $decimals = 2): float
    {
        return round($bytes / self::GIBIBYTE, $decimals);
    }

    public static function toTerabytes(int $bytes, int $decimals = 2): float
    {
        return round($bytes / self::TEBIBYTE, $decimals);
    }

    /**
     * A human-readable size that picks its own unit, for example "1.47 GB".
     */
    public static function humanize(int $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $negative = $bytes < 0;
        $value = (float) abs($bytes);
        $unit = 0;

        while ($value >= self::KIBIBYTE && $unit < count($units) - 1) {
            $value /= self::KIBIBYTE;
            ++$unit;
        }

        return sprintf(
            '%s%s %s',
            $negative ? '-' : '',
            number_format($value, 0 === $unit ? 0 : $decimals, '.', ''),
            $units[$unit],
        );
    }
}
