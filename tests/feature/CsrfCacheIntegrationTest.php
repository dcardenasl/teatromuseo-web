<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/** @internal */
final class CsrfCacheIntegrationTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        Services::reset(true);
        Services::injectMock('cache', new MockCache());
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

    public function testPageCacheDoesNotStoreCsrfCookiesAndEachClientGetsItsOwnToken(): void
    {
        $this->withRoutes([
            ['GET', 'cached-csrf', static function () {
                service('responsecache')->setTtl(300);

                return service('response')->setBody('<form data-public-form></form>');
            }],
        ]);

        $securityConfig = config('Security');

        $first = $this->get('cached-csrf');
        $first->assertStatus(200);
        $first->assertCookie($securityConfig->cookieName);
        $first->assertCookie($securityConfig->readableCookieName);

        $firstNative = $first->response()->getCookie($securityConfig->cookieName);
        $firstMirror = $first->response()->getCookie($securityConfig->readableCookieName);
        $this->assertNotNull($firstNative);
        $this->assertNotNull($firstMirror);
        $this->assertSame($firstNative->getValue(), $firstMirror->getValue());

        $cacheKey = service('responsecache')->generateCacheKey(service('request'));
        $cachedResponse = service('cache')->get($cacheKey);
        $this->assertIsString($cachedResponse);
        $this->assertStringNotContainsString($securityConfig->cookieName, $cachedResponse);
        $this->assertStringNotContainsString($securityConfig->readableCookieName, $cachedResponse);

        // A second client has no cookies but receives the same cached body and
        // a newly generated CSRF pair from the after-filter.
        $_COOKIE = [];
        service('superglobals')->setCookieArray([]);
        Services::resetSingle('incomingrequest');
        Services::resetSingle('security');

        $second = $this->get('cached-csrf');
        $second->assertStatus(200);
        $this->assertSame($first->response()->getBody(), $second->response()->getBody());

        $secondNative = $second->response()->getCookie($securityConfig->cookieName);
        $secondMirror = $second->response()->getCookie($securityConfig->readableCookieName);
        $this->assertNotNull($secondNative);
        $this->assertNotNull($secondMirror);
        $this->assertSame($secondNative->getValue(), $secondMirror->getValue());
        $this->assertNotSame($firstNative->getValue(), $secondNative->getValue());
    }
}
