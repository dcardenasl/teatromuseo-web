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

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['es', 'en']);
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
