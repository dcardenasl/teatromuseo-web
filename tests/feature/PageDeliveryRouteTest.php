<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

final class PageDeliveryRouteTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['aa', 'bb']);
    }

    public function testCmsRouteUsesOneBffPageResolveRequest(): void
    {
        $this->domainAdapter->fakeGet('public-read/aa/page-resolve/about', $this->pageEnvelope('about', 'Fixture about via BFF'));

        $result = $this->get('aa/about');

        $result->assertStatus(200);
        $result->assertSee('Fixture about via BFF');
        self::assertSame(['public-read/aa/page-resolve/about'], $this->domainAdapter->requestedPaths());
        self::assertNotContains('public-read/aa/pages/about', $this->domainAdapter->requestedPaths());
        self::assertNotContains('public-read/aa/page-bootstrap/about', $this->domainAdapter->requestedPaths());
        self::assertNotContains('public-read/aa/layout', $this->domainAdapter->requestedPaths());
    }

    public function testUnlistedRouteAlsoUsesTheBffWithoutAWebResolverFallback(): void
    {
        $this->domainAdapter->fakeGet(
            'public-read/aa/page-resolve/noticias/entrada',
            $this->pageEnvelope('noticias/entrada', 'Fixture unlisted page via BFF'),
        );

        $result = $this->get('aa/noticias/entrada');

        $result->assertStatus(200);
        $result->assertSee('Fixture unlisted page via BFF');
        self::assertSame(['public-read/aa/page-resolve/noticias/entrada'], $this->domainAdapter->requestedPaths());
    }

    public function testPreviewVariantsAreForwardedToTheBff(): void
    {
        $expires = (string) (time() + 600);
        $this->domainAdapter->fakeGet('public-read/aa/page-resolve/about', $this->pageEnvelope('about', 'Fixture draft page'));

        $result = $this->get('aa/about?preview=1&preview_expires=' . $expires . '&preview_sig=' . str_repeat('a', 64));

        $result->assertStatus(200);
        self::assertSame([
            'preview' => '1',
            'preview_expires' => $expires,
            'preview_sig' => str_repeat('a', 64),
        ], $this->domainAdapter->calls[0]['query']);
    }

    public function testBffNotFoundIsRenderedAs404WithoutLocalProbing(): void
    {
        $this->domainAdapter->fakeGet('public-read/aa/page-resolve/missing', [
            'outcome' => 'not_found',
            'page' => null,
            'layout' => [],
            'block_context' => [],
            'meta' => ['locale' => 'aa', 'route' => 'missing'],
            'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            'messages' => ['Public page was not found.'],
        ]);

        $result = $this->get('aa/missing');

        $result->assertStatus(404);
        self::assertSame(['public-read/aa/page-resolve/missing'], $this->domainAdapter->requestedPaths());
    }

    public function testCompletedBffBlockContextRendersWithoutLegacyDomainReads(): void
    {
        $envelope = $this->pageEnvelope('noticias', 'Fixture listing via BFF');
        $envelope['page']['blocks'] = [[
            'block_key' => 'collection_listing',
            'block_config' => [
                'source_type' => 'cms_collection',
                'show_categories' => false,
                'show_tags' => false,
            ],
            'block_data' => [
                'section_title' => 'Entradas BFF',
                'empty_message' => 'Sin entradas',
            ],
            'navigation' => ['url' => '/aa/noticias', 'label' => 'Noticias'],
            'children' => [],
        ]];
        $envelope['block_context']['block_prefetch']['0'] = [
            'ok' => true,
            'status' => 200,
            'data' => [[
                'id' => 1,
                'slug' => 'entrada-bff',
                'title' => 'Entrada entregada por BFF',
                'excerpt' => 'Contenido precompuesto.',
                'published_at' => '2026-01-01T00:00:00+00:00',
                'listing_content' => [],
            ]],
            'meta' => ['pagination' => ['current_page' => 1, 'per_page' => 12, 'total' => 1, 'total_pages' => 1]],
            'facets' => ['categories' => [], 'tags' => []],
            'collection' => [
                'collection_key' => 'noticias',
                'name' => 'Noticias',
                'localized_slugs' => ['aa' => 'noticias'],
                'index_page' => ['localized_urls' => ['aa' => '/aa/noticias']],
            ],
        ];
        $this->domainAdapter->fakeGet('public-read/aa/page-resolve/noticias', $envelope);

        $result = $this->get('aa/noticias');

        $result->assertStatus(200);
        $result->assertSee('Entrada entregada por BFF');
        self::assertSame(['public-read/aa/page-resolve/noticias'], $this->domainAdapter->requestedPaths());
    }

    /** @return array<string, mixed> */
    private function pageEnvelope(string $route, string $title): array
    {
        return [
            'outcome' => 'page',
            'page' => [
                'page_type' => 'cms_page',
                'title' => $title,
                'excerpt' => 'Fixture excerpt.',
                'meta_title' => $title,
                'meta_description' => 'Fixture description.',
                'slug' => $route,
                'localized_slugs' => ['aa' => $route, 'bb' => $route],
                'canonical_url' => '/aa/' . $route,
                'robots' => 'index, follow',
                'blocks' => [],
            ],
            'layout' => [
                'settings' => [],
                'mainMenu' => ['items' => []],
                'footerMenu' => ['items' => []],
                'legalMenu' => ['items' => []],
                'socialLinks' => [],
            ],
            'block_context' => [
                'block_prefetch' => [],
                'block_prefetch_complete' => true,
                'form_definitions' => [],
                'cacheScopes' => [],
            ],
            'meta' => ['locale' => 'aa', 'route' => $route],
            'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            'messages' => [],
        ];
    }
}
