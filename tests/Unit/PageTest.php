<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Tests\Unit;

use BoudhraaDhia7\Vultr\Pagination\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Page::class)]
final class PageTest extends TestCase
{
    public function testItReadsItemsAndMetadata(): void
    {
        $page = Page::fromPayload([
            'instances' => [['id' => 'a'], ['id' => 'b']],
            'meta' => [
                'total' => 7,
                'links' => ['next' => 'next-cursor', 'prev' => 'prev-cursor'],
            ],
        ], 'instances');

        self::assertCount(2, $page);
        self::assertSame(7, $page->total());
        self::assertSame('next-cursor', $page->nextCursor());
        self::assertSame('prev-cursor', $page->previousCursor());
        self::assertTrue($page->hasMore());
        self::assertSame(['id' => 'a'], $page->first());
    }

    public function testEmptyCursorStringsBecomeNull(): void
    {
        $page = Page::fromPayload([
            'instances' => [],
            'meta' => ['total' => 0, 'links' => ['next' => '', 'prev' => '']],
        ], 'instances');

        self::assertNull($page->nextCursor());
        self::assertNull($page->previousCursor());
        self::assertFalse($page->hasMore());
        self::assertTrue($page->isEmpty());
        self::assertNull($page->first());
    }

    public function testItToleratesAMissingEnvelope(): void
    {
        $page = Page::fromPayload([], 'instances');

        self::assertCount(0, $page);
        self::assertNull($page->total());
        self::assertFalse($page->hasMore());
    }

    public function testItIsIterable(): void
    {
        $page = Page::fromPayload(['plans' => [['id' => 'a'], ['id' => 'b']]], 'plans');

        $ids = [];

        foreach ($page as $item) {
            $ids[] = $item['id'];
        }

        self::assertSame(['a', 'b'], $ids);
    }
}
