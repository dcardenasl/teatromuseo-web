<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\CsrfCookieFilter;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/** @internal */
final class CsrfCookieFilterTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_COOKIE = [];
        Services::reset(true);
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        Services::reset(true);

        parent::tearDown();
    }

    public function testEmitsReadableMirrorWithoutWeakeningNativeCookie(): void
    {
        $securityConfig = config('Security');

        $request  = service('request');
        $response = service('response');

        (new CsrfCookieFilter())->after($request, $response);

        $mirror = $response->getCookie($securityConfig->readableCookieName);
        $native = $response->getCookie($securityConfig->cookieName);

        $this->assertNotNull($mirror);
        $this->assertNotNull($native);
        $this->assertSame($native->getValue(), $mirror->getValue());
        $this->assertFalse($mirror->isHTTPOnly());
        $this->assertSame('/', $mirror->getPath());
        $this->assertSame('', $mirror->getDomain());
        $this->assertSame('Lax', $mirror->getSameSite());

        $this->assertTrue($native->isHTTPOnly());
    }
}
