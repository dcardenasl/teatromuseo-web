<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * Feature tests for the POST /cache/invalidate webhook.
 *
 * @internal
 */
final class CacheControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const VALID_KEY = 'test-invalidate-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Services::injectMock('cache', new MockCache());
    }

    protected function tearDown(): void
    {
        $this->unsetInvalidateKey();
        Services::reset(true);

        parent::tearDown();
    }

    private function setInvalidateKey(string $value): void
    {
        putenv("CACHE_INVALIDATE_KEY={$value}");
        $_ENV['CACHE_INVALIDATE_KEY']    = $value;
        $_SERVER['CACHE_INVALIDATE_KEY'] = $value;
    }

    private function unsetInvalidateKey(): void
    {
        putenv('CACHE_INVALIDATE_KEY');
        unset($_ENV['CACHE_INVALIDATE_KEY'], $_SERVER['CACHE_INVALIDATE_KEY']);
    }

    public function testReturns500WhenKeyIsNotConfigured(): void
    {
        $this->unsetInvalidateKey();

        $result = $this->withBodyFormat('json')
            ->post('cache/invalidate', ['scopes' => ['pages']]);

        $result->assertStatus(500);
    }

    public function testReturns401WhenKeyDoesNotMatch(): void
    {
        $this->setInvalidateKey(self::VALID_KEY);

        $result = $this->withHeaders(['X-Invalidate-Key' => 'wrong-key'])
            ->withBodyFormat('json')
            ->post('cache/invalidate', ['scopes' => ['pages']]);

        $result->assertStatus(401);
    }

    public function testReturns422WhenScopesAreEmpty(): void
    {
        $this->setInvalidateKey(self::VALID_KEY);

        $result = $this->withHeaders(['X-Invalidate-Key' => self::VALID_KEY])
            ->withBodyFormat('json')
            ->post('cache/invalidate', ['scopes' => []]);

        $result->assertStatus(422);
    }

    public function testInvalidatesRequestedScopesWithValidKey(): void
    {
        $this->setInvalidateKey(self::VALID_KEY);

        /** @var MockCache $cache */
        $cache = service('cache');
        $cache->save('web_api_v4_pages_abc', ['ok' => true], 300);
        $cache->save('web_api_stale_v4_pages_abc', ['ok' => true], 86400);
        $cache->save('web_api_v4_menus_xyz', ['ok' => true], 300);

        $result = $this->withHeaders(['X-Invalidate-Key' => self::VALID_KEY])
            ->withBodyFormat('json')
            ->post('cache/invalidate', ['scopes' => ['pages']]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['ok' => true, 'invalidated' => ['pages']]);
        $this->assertNull($cache->get('web_api_v4_pages_abc'));
        $this->assertNull($cache->get('web_api_stale_v4_pages_abc'));
        $this->assertNotNull($cache->get('web_api_v4_menus_xyz'));
    }
}
