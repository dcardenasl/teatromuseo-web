<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

/**
 * @internal
 */
final class PublicDetailPagesTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    private bool $originalPageDeliveryEnabled = false;

    private string $originalPageDeliveryMode = 'snapshot';

    private bool $originalPageDeliveryAllowSynchronousFallback = false;

    private bool $originalPageDeliveryBffAllRoutes = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['es', 'en']);
        $config = config('App');
        $this->originalPageDeliveryEnabled = $config->pageDeliveryEnabled;
        $this->originalPageDeliveryMode = $config->pageDeliveryMode;
        $this->originalPageDeliveryAllowSynchronousFallback = $config->pageDeliveryAllowSynchronousFallback;
        $this->originalPageDeliveryBffAllRoutes = $config->pageDeliveryBffAllRoutes;
    }

    protected function tearDown(): void
    {
        $config = config('App');
        $config->pageDeliveryEnabled = $this->originalPageDeliveryEnabled;
        $config->pageDeliveryMode = $this->originalPageDeliveryMode;
        $config->pageDeliveryAllowSynchronousFallback = $this->originalPageDeliveryAllowSynchronousFallback;
        $config->pageDeliveryBffAllRoutes = $this->originalPageDeliveryBffAllRoutes;

        parent::tearDown();
    }

    public function testMuseumDetailPageRendersCmsTemplateBlocks(): void
    {
        $this->seedMuseumDetail();

        $result = $this->get($this->locale() . '/museo/coleccion/TMP-001');

        $result->assertStatus(200);

        $body = $result->response()->getBody();
        $this->assertStringContainsString('Pieza localizada', $body);
        $this->assertStringContainsString('Resumen localizado', $body);
        $this->assertStringContainsString('Categoría localizada', $body);
        $this->assertStringContainsString('Técnica localizada', $body);
        $this->assertStringNotContainsString('English category', $body);
        $this->assertStringNotContainsString('English technique', $body);
        $this->assertStringContainsString('Vista previa integrada', $body);
        $this->assertStringContainsString('Imágenes de colección', $body);
        $this->assertSame(1, $this->countCalls('public-read/es/collection-items/TMP-001'));
    }

    public function testEventDetailPageRendersCmsTemplateBlocks(): void
    {
        $this->seedEventDetail();

        $result = $this->get($this->locale() . '/cartelera/festival-uno');

        $result->assertStatus(200);

        $body = $result->response()->getBody();
        $this->assertStringContainsString('Festival Uno', $body);
        $this->assertStringContainsString('Descripción del festival uno.', $body);
        $this->assertStringContainsString('Festival', $body);
        $this->assertStringContainsString('Inicio:', $body);
        $this->assertStringContainsString('Fin:', $body);
        $this->assertSame(1, $this->countCalls('public-read/es/events/festival-uno'));
    }

    public function testMuseumDetailPageUsesTheBffEnvelopeWithoutWebDomainReads(): void
    {
        $this->enableFullPageBff();
        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/museo/coleccion/pieza-de-prueba',
            $this->bffEnvelope(
                route: 'museo/coleccion/pieza-de-prueba',
                pageType: 'template_catalog_item',
                title: 'Pieza localizada',
                excerpt: 'Resumen localizado.',
                blocks: [['block_key' => 'catalog_item_header']],
                contextKey: 'catalog_item',
                context: [
                    'id' => 101,
                    'name' => 'Pieza de prueba',
                    'localized' => [
                        'name' => 'Pieza localizada',
                        'summary' => 'Resumen localizado.',
                    ],
                ],
            ),
        );

        $result = $this->get($this->locale() . '/museo/coleccion/pieza-de-prueba');

        $result->assertStatus(200);
        $body = $result->response()->getBody();
        $this->assertStringContainsString('Pieza localizada', $body);
        $this->assertStringContainsString('Resumen localizado.', $body);
        $this->assertSame(1, $this->countCalls('public-read/es/page-resolve/museo/coleccion/pieza-de-prueba'));
        $this->assertSame(0, $this->countCalls('public-read/es/collection-items/pieza-de-prueba'));
        $this->assertSame(0, $this->countCalls('public/es/pages/by-type/template_catalog_item'));
        $this->assertNotContains('public/catalog/categories', $this->domainAdapter->requestedPaths());
    }

    public function testEventDetailPageUsesTheBffEnvelopeWithoutWebDomainReads(): void
    {
        $this->enableFullPageBff();
        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/cartelera/festival-uno',
            $this->bffEnvelope(
                route: 'cartelera/festival-uno',
                pageType: 'template_event_item',
                title: 'Festival Uno',
                excerpt: 'Descripción del festival uno.',
                blocks: [['block_key' => 'event_item_header']],
                contextKey: 'event_item',
                context: [
                    'id' => 201,
                    'title' => 'Festival Uno',
                    'event_type' => 'festival',
                    'description' => 'Descripción del festival uno.',
                    'localized' => [
                        'title' => 'Festival Uno',
                        'description' => 'Descripción del festival uno.',
                    ],
                    'occurrences' => [[
                        'start_time' => '2026-08-01 19:00:00',
                        'end_time' => '2026-08-01 21:00:00',
                        'venue_name' => 'Sala Principal',
                        'timezone' => 'America/Santiago',
                    ]],
                ],
            ),
        );

        $result = $this->get($this->locale() . '/cartelera/festival-uno');

        $result->assertStatus(200);
        $body = $result->response()->getBody();
        $this->assertStringContainsString('Festival Uno', $body);
        $this->assertStringContainsString('Descripción del festival uno.', $body);
        $this->assertSame(1, $this->countCalls('public-read/es/page-resolve/cartelera/festival-uno'));
        $this->assertSame(0, $this->countCalls('public-read/es/events/festival-uno'));
        $this->assertSame(0, $this->countCalls('public/es/pages/by-type/template_event_item'));
    }

    private function enableFullPageBff(): void
    {
        config('App')->pageDeliveryEnabled = true;
        config('App')->pageDeliveryMode = 'sync';
        config('App')->pageDeliveryAllowSynchronousFallback = false;
        config('App')->pageDeliveryBffAllRoutes = true;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function bffEnvelope(
        string $route,
        string $pageType,
        string $title,
        string $excerpt,
        array $blocks,
        string $contextKey,
        array $context,
    ): array {
        return [
            'outcome' => 'page',
            'redirect' => null,
            'page' => [
                'page_type' => 'cms_page',
                'source_page_type' => $pageType,
                'title' => $title,
                'excerpt' => $excerpt,
                'meta_title' => $title,
                'meta_description' => $excerpt,
                'slug' => $route,
                'localized_slugs' => ['es' => $route],
                'canonical_url' => '/es/' . $route,
                'robots' => 'index, follow',
                'blocks' => $blocks,
            ],
            'layout' => [
                'settings' => [],
                'mainMenu' => ['items' => []],
                'footerMenu' => ['items' => []],
                'legalMenu' => ['items' => []],
                'socialLinks' => [],
            ],
            'block_context' => [
                $contextKey => $context,
                'block_prefetch' => [],
                'block_prefetch_complete' => true,
                'form_definitions' => [],
                'cacheScopes' => [],
            ],
            'meta' => ['locale' => 'es', 'route' => $route],
            'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            'messages' => [],
        ];
    }

    private function countCalls(string $path): int
    {
        return count(array_filter(
            $this->domainAdapter->calls,
            static fn (array $call): bool => ($call['path'] ?? '') === $path,
        ));
    }

    private function seedMuseumDetail(): void
    {
        $templateBlocks = [
            [
                'block_key' => 'catalog_item_header',
                'block_config' => [],
                'block_data' => [],
                'children' => [],
            ],
            [
                'block_key' => 'catalog_item_gallery',
                'block_config' => [],
                'block_data' => [],
                'children' => [],
            ],
            [
                'block_key' => 'catalog_item_details',
                'block_config' => [],
                'block_data' => [],
                'children' => [],
            ],
        ];

        $this->domainAdapter->fakeGet('public/catalog/categories', [
            ['id' => 1, 'slug' => 'categorias', 'name' => 'English category', 'localized' => ['name' => 'Categoría localizada']],
        ]);

        $this->domainAdapter->fakeGet('public-read/es/collection-items/TMP-001', [
            'id' => 101,
            'name' => 'Pieza de prueba',
            'inventory_code' => 'TMP-001',
            'summary' => 'Resumen temporal',
            'localized' => [
                'name' => 'Pieza localizada',
                'summary' => 'Resumen localizado',
                'ubicacion' => 'Ubicación localizada',
                'curiosidad' => 'Curiosidad localizada',
            ],
            'category_id' => 1,
            'techniques' => [
                ['id' => 3, 'name' => 'English technique', 'localized' => ['name' => 'Técnica localizada']],
            ],
            'cover_image' => [
                'url' => 'https://example.com/obra-prueba.jpg',
                'variants' => [],
            ],
            'gallery_images' => [
                ['url' => 'https://example.com/obra-prueba-1.jpg'],
                ['url' => 'https://example.com/obra-prueba-2.jpg'],
            ],
            'status' => 'published',
            'is_active' => 1,
        ]);

        $this->domainAdapter->fakeGet('public/es/pages/by-type/template_catalog_item', [
            'title' => 'Plantilla de ficha de catálogo',
            'slug' => '__template_catalog_item',
            'excerpt' => 'Plantilla interna para la ficha pública del catálogo.',
            'meta_title' => 'Plantilla de ficha de catálogo',
            'meta_description' => 'Plantilla interna para la ficha pública del catálogo.',
            'canonical_url' => '',
            'robots' => 'noindex, follow',
            'is_in_sitemap' => false,
            'updated_at' => '2026-01-01T00:00:00+00:00',
            'sitemap_changefreq' => 'never',
            'sitemap_priority' => '0.0',
            'blocks' => $templateBlocks,
            'localized_slugs' => [
                'es' => '__template_catalog_item',
                'en' => '__template_catalog_item',
            ],
        ]);
    }

    private function seedEventDetail(): void
    {
        $templateBlocks = [
            [
                'block_key' => 'event_item_header',
                'block_config' => [],
                'block_data' => [],
                'children' => [],
            ],
        ];

        $this->domainAdapter->fakeGet('public-read/es/events/festival-uno', [
            'id' => 201,
            'uuid' => 'evt-201',
            'title' => 'Festival Uno',
            'event_type' => 'festival',
            'description' => 'Descripción del festival uno.',
            'localized' => [
                'title' => 'Festival Uno',
                'description' => 'Descripción del festival uno.',
            ],
            'occurrences' => [[
                'start_time' => '2026-08-01 19:00:00',
                'end_time' => '2026-08-01 21:00:00',
                'venue_name' => 'Sala Principal',
                'timezone' => 'America/Santiago',
            ]],
            'status' => 'published',
            'cover_image' => [
                'url' => 'https://example.com/festival-uno.jpg',
                'variants' => [],
            ],
        ]);

        $this->domainAdapter->fakeGet('public/es/pages/by-type/template_event_item', [
            'title' => 'Plantilla de ficha de evento',
            'slug' => '__template_event_item',
            'excerpt' => 'Plantilla interna para la ficha pública de programación.',
            'meta_title' => 'Plantilla de ficha de evento',
            'meta_description' => 'Plantilla interna para la ficha pública de programación.',
            'canonical_url' => '',
            'robots' => 'noindex, follow',
            'is_in_sitemap' => false,
            'updated_at' => '2026-01-01T00:00:00+00:00',
            'sitemap_changefreq' => 'never',
            'sitemap_priority' => '0.0',
            'blocks' => $templateBlocks,
            'localized_slugs' => [
                'es' => '__template_event_item',
                'en' => '__template_event_item',
            ],
        ]);
    }
}
