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

    public function testInvalidateHomeAlsoClearsLocalizedHomepageResponseCache(): void
    {
        $this->cache->save(md5('GET:/es'), 'root page', 900);
        $this->cache->save(md5('GET:/es/inicio'), 'localized page', 900);
        $this->cache->save(md5('GET:/es/public/es'), 'legacy leaked page', 900);

        $result = $this->invalidator->invalidate(['pages'], 'remote', ['es'], ['home']);

        $this->assertSame(3, $result['response_cache_deleted']);
        $this->assertNull($this->cache->get(md5('GET:/es')));
        $this->assertNull($this->cache->get(md5('GET:/es/inicio')));
        $this->assertNull($this->cache->get(md5('GET:/es/public/es')));
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
        $this->assertContains('events', $scopes);
        $this->assertContains('categories', $scopes);
        $this->assertContains('techniques', $scopes);
        $this->assertContains('collection_items', $scopes);
        $this->assertCount(13, $scopes);
    }

    public function testStatusTracksAutomaticAndManualInvalidations(): void
    {
        putenv('CACHE_INVALIDATE_KEY=test-secret');
        $_ENV['CACHE_INVALIDATE_KEY'] = 'test-secret';

        $this->invalidator->invalidate(['pages'], 'cms_automatic');
        $automatic = $this->invalidator->status();

        $this->assertNotNull($automatic['last_automatic_invalidation_at']);
        $this->assertSame('cms_automatic', $automatic['last_invalidation_source']);
        $this->assertSame(['pages'], $automatic['last_invalidation_scopes']);

        $this->invalidator->invalidate(['menus'], 'admin_manual');
        $manual = $this->invalidator->status();

        $this->assertSame($automatic['last_automatic_invalidation_at'], $manual['last_automatic_invalidation_at']);
        $this->assertNotNull($manual['last_manual_invalidation_at']);
        $this->assertSame('admin_manual', $manual['last_invalidation_source']);

        putenv('CACHE_INVALIDATE_KEY');
        unset($_ENV['CACHE_INVALIDATE_KEY']);
    }
}
