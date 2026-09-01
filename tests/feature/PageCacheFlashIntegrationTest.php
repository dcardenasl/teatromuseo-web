<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\BasePublicWebController;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * A render() reached while a session is active may bake visitor-specific
 * flashdata into the HTML (the form_sent_ and form_errors_ keys from
 * FormController.php / form_embed.php, and the generic success/error/warning
 * keys read by flash_messages.php on every public page). Such a response must never enter
 * the shared, cookie-blind ResponseCache: an earlier flash-less render would
 * otherwise mask a later visitor's confirmation, or a flash-bearing render
 * would leak one visitor's message to everyone else for the cache TTL.
 *
 * @internal
 */
final class PageCacheFlashIntegrationTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        Services::reset(true);
        Services::injectMock('cache', new MockCache());
        config('App')->webPageCacheTtl = 300;
        $_COOKIE = [];
        service('superglobals')->setCookieArray([]);
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        service('superglobals')->setCookieArray([]);
        Services::reset(true);

        parent::tearDown();
    }

    public function testRenderWithActiveSessionIsNeverWrittenToResponseCache(): void
    {
        $_COOKIE[config('Session')->cookieName] = bin2hex(random_bytes(20));
        service('superglobals')->setCookieArray($_COOKIE);

        $this->withRoutes([
            ['GET', 'render-with-session', [TestRenderController::class, 'index']],
        ]);

        $result = $this->get('render-with-session');

        $result->assertStatus(200);
        $result->assertHeader('Cache-Control', 'no-store, private');

        $cacheKey = service('responsecache')->generateCacheKey(service('request'));
        $this->assertNull(service('cache')->get($cacheKey));
    }

    public function testRenderWithoutSessionIsCacheableAsBefore(): void
    {
        $this->withRoutes([
            ['GET', 'render-without-session', [TestRenderController::class, 'index']],
        ]);

        $result = $this->get('render-without-session');

        $result->assertStatus(200);
        $result->assertHeaderContains('Cache-Control', 'public');

        $cacheKey = service('responsecache')->generateCacheKey(service('request'));
        $this->assertIsString(service('cache')->get($cacheKey));
    }
}

/** @internal test-only controller exercising BasePublicWebController::render() */
final class TestRenderController extends BasePublicWebController
{
    public function index()
    {
        return $this->render('errors/404', ['message' => 'test']);
    }
}
