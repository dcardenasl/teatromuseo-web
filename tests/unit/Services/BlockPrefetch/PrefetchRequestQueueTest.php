<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BlockPrefetch;

use App\Services\BlockPrefetch\PrefetchRequestQueue;
use App\Services\BlockPrefetch\RequestQueryReader;
use PHPUnit\Framework\TestCase;

final class PrefetchRequestQueueTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['preview'], $_GET['preview_expires'], $_GET['preview_sig']);
        \Config\Services::reset(true);
        parent::tearDown();
    }

    public function testIdenticalRequestsAreDeduplicatedToTheSameIndex(): void
    {
        $queue = new PrefetchRequestQueue('es', new RequestQueryReader());

        $first = $queue->add('cms', 'public-read/es/entries/news', ['page' => 1], 180, 'entries');
        $second = $queue->add('cms', 'public-read/es/entries/news', ['page' => 1], 180, 'entries');

        $this->assertSame($first, $second);
        $this->assertCount(1, $queue->all());
    }

    public function testQueryKeyOrderDoesNotAffectDeduplication(): void
    {
        $queue = new PrefetchRequestQueue('es', new RequestQueryReader());

        $first = $queue->add('cms', 'p', ['a' => 1, 'b' => 2], 60, 'scope');
        $second = $queue->add('cms', 'p', ['b' => 2, 'a' => 1], 60, 'scope');

        $this->assertSame($first, $second);
    }

    public function testDifferentLocaleClientOrScopeAreNotDeduplicated(): void
    {
        $queue = new PrefetchRequestQueue('es', new RequestQueryReader());

        $a = $queue->add('cms', 'p', [], 60, 'scope-a');
        $b = $queue->add('cms', 'p', [], 60, 'scope-b');
        $c = $queue->add('catalog', 'p', [], 60, 'scope-a');

        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertCount(3, $queue->all());
    }

    public function testPreviewRequestForcesZeroCacheTtlAndInjectsPreviewParams(): void
    {
        $_GET['preview'] = '1';
        $_GET['preview_sig'] = 'abc123';
        \Config\Services::reset(true);

        $queue = new PrefetchRequestQueue('es', new RequestQueryReader());
        $queue->add('cms', 'public-read/es/pages/nosotros', [], 3600, 'pages');

        $request = $queue->all()[0];
        $this->assertSame(0, $request['cacheTtl']);
        $this->assertSame('1', $request['query']['preview']);
        $this->assertSame('abc123', $request['query']['preview_sig']);
    }
}
