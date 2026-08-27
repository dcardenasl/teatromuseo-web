<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * Stock CodeIgniter\Filters\PageCache::before() looks up the shared
 * ResponseCache by method+URI alone and returns a hit before the controller
 * ever runs — before BasePublicWebController::render() gets a chance to
 * apply its own "don't use the cache while a session is active" rule (see
 * PageCacheFlashIntegrationTest). A stale entry cached from an earlier
 * anonymous visit would then be replayed to a visitor who just submitted a
 * form and has visitor-specific flashdata waiting. SessionAwarePageCache
 * closes that gap by skipping the cache lookup itself when a session cookie
 * is present, so the request reaches the controller for a fresh render.
 *
 * @internal
 */
final class SessionAwarePageCacheTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public static int $renderCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        self::$renderCount = 0;
        Services::reset(true);
        Services::injectMock('cache', new MockCache());
        $_COOKIE = [];
        service('superglobals')->setCookieArray([]);

        $this->withRoutes([
            ['GET', 'session-aware-cache-test', static function (): ResponseInterface {
                self::$renderCount++;
                service('responsecache')->setTtl(300);

                return service('response')->setBody('render-' . self::$renderCount);
            }],
        ]);
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        service('superglobals')->setCookieArray([]);
        Services::reset(true);

        parent::tearDown();
    }

    public function testAnonymousRequestReceivesTheCachedHitAndNeverRerendersServer(): void
    {
        $first = $this->get('session-aware-cache-test');
        $first->assertSee('render-1');

        $second = $this->get('session-aware-cache-test');
        $second->assertSee('render-1');
        $this->assertSame(1, self::$renderCount, 'the second anonymous hit must be served from cache, not re-rendered');
    }

    public function testActiveSessionSkipsAStalePreExistingCacheHit(): void
    {
        // Seed the cache exactly as an earlier anonymous visit would — this
        // is the "generic, no flash" entry that masked a visitor's
        // confirmation in the original bug.
        $primed = $this->get('session-aware-cache-test');
        $primed->assertSee('render-1');

        $_COOKIE[config('Session')->cookieName] = bin2hex(random_bytes(20));
        service('superglobals')->setCookieArray($_COOKIE);

        $withSession = $this->get('session-aware-cache-test');

        $withSession->assertSee('render-2');
        $this->assertSame(2, self::$renderCount, 'a session-bearing request must bypass the cache and hit the controller');
    }
}
