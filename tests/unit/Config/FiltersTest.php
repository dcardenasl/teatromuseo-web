<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Config\Filters;
use PHPUnit\Framework\TestCase;

final class FiltersTest extends TestCase
{
    public function testTrackingIsRequiredAfterPageCacheAndNotARegularGlobalFilter(): void
    {
        $filters = new Filters();
        $afterRequired = $filters->required['after'];

        $pageCachePosition = array_search('pagecache', $afterRequired, true);
        $trackingPosition = array_search('tracking', $afterRequired, true);

        $this->assertIsInt($pageCachePosition);
        $this->assertIsInt($trackingPosition);
        $this->assertLessThan($trackingPosition, $pageCachePosition);
        $this->assertNotContains('tracking', $filters->globals['after']);
    }
}
