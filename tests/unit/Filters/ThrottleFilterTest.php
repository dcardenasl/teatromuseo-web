<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\ThrottleFilter;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * @internal
 */
final class ThrottleFilterTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Services::injectMock('cache', new MockCache());
        putenv('WEB_THROTTLE_IN_TESTS=true');
        $_ENV['WEB_THROTTLE_IN_TESTS']    = 'true';
        $_SERVER['WEB_THROTTLE_IN_TESTS'] = 'true';
    }

    protected function tearDown(): void
    {
        putenv('WEB_THROTTLE_IN_TESTS');
        unset($_ENV['WEB_THROTTLE_IN_TESTS'], $_SERVER['WEB_THROTTLE_IN_TESTS']);
        Services::reset(true);

        parent::tearDown();
    }

    public function testAllowsRequestsWithinCapacity(): void
    {
        $filter  = new ThrottleFilter();
        $request = service('request');

        $this->assertNull($filter->before($request, ['2', '60']));
        $this->assertNull($filter->before($request, ['2', '60']));
    }

    public function testBlocksRequestsOverCapacityWith429(): void
    {
        $filter  = new ThrottleFilter();
        $request = service('request');

        $filter->before($request, ['2', '60']);
        $filter->before($request, ['2', '60']);
        $result = $filter->before($request, ['2', '60']);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testIsBypassedInTestingEnvironmentByDefault(): void
    {
        putenv('WEB_THROTTLE_IN_TESTS');
        unset($_ENV['WEB_THROTTLE_IN_TESTS'], $_SERVER['WEB_THROTTLE_IN_TESTS']);

        $filter  = new ThrottleFilter();
        $request = service('request');

        // Capacity 0 would block immediately if the filter were active.
        $this->assertNull($filter->before($request, ['0', '60']));
    }
}
