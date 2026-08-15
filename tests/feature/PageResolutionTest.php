<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

final class PageResolutionTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['aa', 'bb']);
    }

    public function testEveryDynamicRouteUsesTheBffResolver(): void
    {
        $this->domainAdapter->fakeGet('public-read/aa/page-resolve/nosotros', [
            'outcome' => 'page',
            'page' => [
                'page_type' => 'cms_page',
                'title' => 'Fixture nosotros',
                'excerpt' => 'Fixture excerpt.',
                'meta_title' => 'Fixture nosotros',
                'meta_description' => 'Fixture description.',
                'slug' => 'nosotros',
                'localized_slugs' => ['aa' => 'nosotros'],
                'canonical_url' => '/aa/nosotros',
                'robots' => 'index, follow',
                'blocks' => [],
            ],
            'layout' => ['settings' => [], 'mainMenu' => ['items' => []], 'footerMenu' => ['items' => []], 'legalMenu' => ['items' => []], 'socialLinks' => []],
            'block_context' => ['block_prefetch' => [], 'block_prefetch_complete' => true, 'form_definitions' => []],
            'meta' => ['locale' => 'aa', 'route' => 'nosotros'],
            'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            'messages' => [],
        ]);

        $result = $this->get('aa/nosotros');

        $result->assertStatus(200);
        $result->assertSee('Fixture nosotros');
        self::assertSame(['public-read/aa/page-resolve/nosotros'], $this->domainAdapter->requestedPaths());
    }

    public function testBffRedirectAndNotFoundOutcomesArePreserved(): void
    {
        $this->domainAdapter->fakeGet('public-read/aa/page-resolve/legacy', [
            'outcome' => 'redirect',
            'redirect' => ['path' => '/nosotros', 'status' => 301],
            'page' => null,
            'layout' => [],
            'block_context' => [],
            'meta' => ['locale' => 'aa', 'route' => 'legacy'],
            'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            'messages' => [],
        ]);

        $result = $this->get('aa/legacy');

        $result->assertStatus(301);
        $result->assertHeader('Location', 'http://localhost:8184/aa/nosotros');
    }
}
