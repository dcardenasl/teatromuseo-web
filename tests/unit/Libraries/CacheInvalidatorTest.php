<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\CacheInvalidator;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * @internal
 */
final class CacheInvalidatorTest extends CIUnitTestCase
{
    private MockCache $cache;
    private CacheInvalidator $invalidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new MockCache();
        Services::injectMock('cache', $this->cache);
        $this->invalidator = new CacheInvalidator();
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testInvalidateDeletesFreshAndStaleKeysForScope(): void
    {
        $this->cache->save('web_api_v4_pages_abc123', ['ok' => true], 300);
        $this->cache->save('web_api_stale_v4_pages_abc123', ['ok' => true], 86400);
        $this->cache->save('web_api_v4_menus_def456', ['ok' => true], 300);

        $result = $this->invalidator->invalidate(['pages']);

        $this->assertSame(['pages'], $result['invalidated']);
        $this->assertSame(2, $result['deleted']);
        $this->assertNull($this->cache->get('web_api_v4_pages_abc123'));
        $this->assertNull($this->cache->get('web_api_stale_v4_pages_abc123'));
        $this->assertNotNull($this->cache->get('web_api_v4_menus_def456'), 'Other scopes must be untouched');
    }

    public function testInvalidateClearsSitemapsOnContentScopes(): void
    {
        $this->cache->save('sitemap_es', 'xml...', 3600);
        $this->cache->save('sitemap_en', 'xml...', 3600);
        $this->cache->save('web_api_v4_pages_abc123', ['ok' => true], 300);

        $this->invalidator->invalidate(['pages']);

        $this->assertNull($this->cache->get('sitemap_es'));
        $this->assertNull($this->cache->get('sitemap_en'));
    }

    public function testInvalidateDoesNotClearSitemapsOnNonContentScopes(): void
    {
        $this->cache->save('sitemap_es', 'xml...', 3600);
        $this->cache->save('web_api_v4_menus_abc123', ['ok' => true], 300);

        $this->invalidator->invalidate(['menus']);

        $this->assertNotNull($this->cache->get('sitemap_es'));
    }

    public function testUnknownScopesAreIgnored(): void
    {
        $this->cache->save('web_api_v4_pages_abc123', ['ok' => true], 300);

        $result = $this->invalidator->invalidate(['bogus', 'pages']);

        $this->assertSame(['pages'], $result['invalidated']);
        $this->assertSame(1, $result['deleted']);
    }

    public function testEmptyScopeListInvalidatesNothing(): void
    {
        $result = $this->invalidator->invalidate([]);

        $this->assertSame([], $result['invalidated']);
        $this->assertSame(0, $result['deleted']);
    }

    public function testValidScopesExposesTheWhitelist(): void
    {
        $scopes = CacheInvalidator::validScopes();

        $this->assertContains('pages', $scopes);
        $this->assertContains('forms', $scopes);
        $this->assertCount(8, $scopes);
    }
}
