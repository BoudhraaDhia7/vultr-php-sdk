<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Unit;

use BoudhraaDhia7\Vultr\Util\Bytes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bytes::class)]
final class BytesTest extends TestCase
{
    public function testItConvertsToBinaryUnits(): void
    {
        self::assertSame(1.0, Bytes::toKilobytes(1024));
        self::assertSame(1.0, Bytes::toMegabytes(1024 ** 2));
        self::assertSame(1.0, Bytes::toGigabytes(1024 ** 3));
        self::assertSame(1.0, Bytes::toTerabytes(1024 ** 4));
    }

    public function testItHonoursTheRequestedPrecision(): void
    {
        self::assertSame(1.5, Bytes::toGigabytes(1_610_612_736, 1));
        self::assertSame(1.5, Bytes::toGigabytes(1_610_612_736, 4));
    }

    /**
     * The v1 helper ran its result through number_format() before casting back
     * to float, so any value over 999 was silently truncated at the thousands
     * separator: 1 GiB came back from toMegabytes() as 1.0 instead of 1024.0.
     */
    public function testLargeValuesAreNotTruncatedByAThousandsSeparator(): void
    {
        self::assertSame(1024.0, Bytes::toMegabytes(1024 ** 3));
        self::assertSame(2048.0, Bytes::toKilobytes(2 * 1024 ** 2));
    }

    #[DataProvider('humanReadableSizes')]
    public function testItHumanizes(int $bytes, string $expected): void
    {
        self::assertSame($expected, Bytes::humanize($bytes));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function humanReadableSizes(): iterable
    {
        yield 'zero' => [0, '0 B'];
        yield 'bytes' => [512, '512 B'];
        yield 'kilobytes' => [2048, '2.00 KB'];
        yield 'megabytes' => [5 * 1024 ** 2, '5.00 MB'];
        yield 'gigabytes' => [1_610_612_736, '1.50 GB'];
        yield 'negative' => [-1024, '-1.00 KB'];
    }
}
