<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * IP-based rate limiting using CI4's native Throttler (token bucket).
 *
 * Usage in routes: ['filter' => 'throttle:10,60'] → 10 requests per 60 seconds.
 * Applied to public POST endpoints only (form submissions, cache webhook) so
 * legitimate crawlers hitting GET pages are never penalized.
 */
class ThrottleFilter implements FilterInterface
{
    private const DEFAULT_CAPACITY = 30;
    private const DEFAULT_SECONDS  = 60;

    /**
     * @param list<string>|null $arguments [capacity, seconds]
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Feature tests exercise controllers directly; dedicated throttle tests
        // opt back in through Config\App::$throttleInTestsEnabled (WEB_THROTTLE_IN_TESTS).
        // Read a fresh, unshared Config\App instance — tests toggle the env var
        // per-test at runtime and the shared config() instance would not
        // re-read it.
        if (ENVIRONMENT === 'testing' && ! (new \Config\App())->throttleInTestsEnabled) {
            return null;
        }

        $capacity = (int) ($arguments[0] ?? self::DEFAULT_CAPACITY);
        $seconds  = (int) ($arguments[1] ?? self::DEFAULT_SECONDS);

        $bucket = 'throttle_' . md5($request->getIPAddress());

        if (service('throttler')->check($bucket, $capacity, $seconds) === false) {
            return service('response')
                ->setStatusCode(429)
                ->setJSON(['ok' => false, 'message' => 'Too Many Requests']);
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
