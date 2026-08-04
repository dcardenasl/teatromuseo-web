<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

/**
 * @internal
 */
final class PublicListingPagesTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['es', 'en']);
    }

    public function testMuseumListingPageRendersCardsFiltersAndPagination(): void
    {
        $this->seedMuseumCatalog();

        $result = $this->get($this->locale() . '/museo/coleccion');

        $result->assertStatus(200);

        $body = $result->response()->getBody();
        $this->assertStringContainsString('Colección del museo', $body);
        $this->assertStringContainsString('Buscar', $body);
        $this->assertStringContainsString('Obras', $body);
        $this->assertStringContainsString('Ver ficha', $body);
        $this->assertStringContainsString('/es/museo/coleccion/obra-uno', $body);
        $this->assertStringContainsString('data-listing-pagination', $body);
        // Numbered pagination: current page (1) marked, page 2 reachable directly.
        $this->assertStringContainsString('aria-current="page"', $body);
        $this->assertMatchesRegularExpression('/href="[^"]*page=2[^"]*"/', $body);
        $this->assertStringContainsString('Siguiente', $body);
        $this->assertStringContainsString('hreflang="es"', $body);
        $this->assertStringContainsString('hreflang="en"', $body);
    }

    public function testMuseumListingPagePrefersCmsPageWhenAvailable(): void
    {
        $this->seedMuseumCatalog();

        $this->domainAdapter->fakeGet($this->domainPath('pages/museo/coleccion'), $this->page('museo/coleccion', 'Colección del museo CMS', [
            'excerpt' => 'Contenido controlado desde el CMS.',
            'meta_description' => 'Contenido controlado desde el CMS.',
            'localized_slugs' => [
                'es' => 'museo/coleccion',
                'en' => 'museum/collection',
            ],
            'blocks' => [[
                'block_key' => 'collection_listing',
                'block_config' => [
                    'source_type' => 'catalog_items',
                    'per_page' => 12,
                    'order_by' => 'name',
                    'order_direction' => 'asc',
                    'layout_variant' => 'cards',
                    'css_class' => 'public-listing public-listing--museum',
                    'show_search' => true,
                    'show_categories' => true,
                    'show_tags' => false,
                    'show_excerpt' => true,
                    'show_date' => false,
                    'show_button' => true,
                    'show_item_categories' => true,
                    'show_extra_richtext' => false,
                    'show_extra_link' => false,
                    'show_extra_image' => false,
                    'section_label' => 'Museo',
                    'intro_title' => 'Colección del museo CMS',
                    'intro_text' => '<p>Contenido controlado desde el CMS.</p>',
                    'item_label' => 'Obra',
                    'featured_item_label' => 'Obra destacada',
                    'count_label' => 'Mostrando {count} obras',
                    'entry_cta_label' => 'Ver ficha',
                    'empty_message' => 'No hay obras disponibles todavía.',
                ],
                'block_data' => [],
                'navigation' => [
                    'status' => 'resolved',
                    'target_type' => 'listing_page',
                    'target_id' => 1,
                    'url' => '/es/museo/coleccion',
                ],
                'children' => [],
            ]],
        ]));

        $result = $this->get($this->locale() . '/museo/coleccion');

        $result->assertStatus(200);

        $body = $result->response()->getBody();
        $this->assertStringContainsString('Colección del museo CMS', $body);
        $this->assertStringContainsString('Contenido controlado desde el CMS.', $body);
        $this->assertStringContainsString('Obra Uno', $body);
        $this->assertStringContainsString('/es/museo/coleccion/obra-uno', $body);
    }

    public function testEventListingPageRendersCardsFiltersAndPagination(): void
    {
        $this->seedEventListing();

        $result = $this->get($this->locale() . '/cartelera');

        $result->assertStatus(200);

        $body = $result->response()->getBody();
        $this->assertStringContainsString('Cartelera', $body);
        $this->assertStringContainsString('Programación', $body);
        $this->assertStringContainsString('Buscar', $body);
        $this->assertStringContainsString('#Festival', $body);
        $this->assertStringContainsString('Ver evento', $body);
        $this->assertStringContainsString('/es/cartelera/festival-uno', $body);
        $this->assertStringContainsString('data-listing-pagination', $body);
        // Numbered pagination: current page (1) marked, page 2 reachable directly.
        $this->assertStringContainsString('aria-current="page"', $body);
        $this->assertMatchesRegularExpression('/href="[^"]*page=2[^"]*"/', $body);
        $this->assertStringContainsString('Siguiente', $body);
        $this->assertStringContainsString('hreflang="es"', $body);
        $this->assertStringContainsString('hreflang="en"', $body);
    }

    public function testEventListingPagePrefersCmsPageWhenAvailable(): void
    {
        $this->seedEventListing();

        $this->domainAdapter->fakeGet($this->domainPath('pages/cartelera'), $this->page('cartelera', 'Cartelera CMS', [
            'excerpt' => 'Programación controlada desde el CMS.',
            'meta_description' => 'Programación controlada desde el CMS.',
            'localized_slugs' => [
                'es' => 'cartelera',
                'en' => 'cartelera',
            ],
            'blocks' => [[
                'block_key' => 'collection_listing',
                'block_config' => [
                    'source_type' => 'event_items',
                    'per_page' => 12,
                    'order_by' => 'start_time',
                    'order_direction' => 'asc',
                    'layout_variant' => 'cards',
                    'css_class' => 'public-listing public-listing--event',
                    'show_search' => true,
                    'show_categories' => false,
                    'show_tags' => true,
                    'show_excerpt' => true,
                    'show_date' => true,
                    'show_button' => true,
                    'show_item_categories' => true,
                    'show_extra_richtext' => false,
                    'show_extra_link' => false,
                    'show_extra_image' => false,
                    'section_label' => 'Programación',
                    'intro_title' => 'Cartelera CMS',
                    'intro_text' => '<p>Programación controlada desde el CMS.</p>',
                    'item_label' => 'Evento',
                    'featured_item_label' => 'Evento destacado',
                    'count_label' => 'Mostrando {count} eventos',
                    'entry_cta_label' => 'Ver evento',
                    'empty_message' => 'No hay eventos disponibles todavía.',
                ],
                'block_data' => [],
                'navigation' => [
                    'status' => 'resolved',
                    'target_type' => 'listing_page',
                    'target_id' => 1,
                    'url' => '/es/cartelera',
                ],
                'children' => [],
            ]],
        ]));

        $result = $this->get($this->locale() . '/cartelera');

        $result->assertStatus(200);

        $body = $result->response()->getBody();
        $this->assertStringContainsString('Cartelera CMS', $body);
        $this->assertStringContainsString('Programación controlada desde el CMS.', $body);
        $this->assertStringContainsString('Festival Uno', $body);
        $this->assertStringContainsString('/es/cartelera/festival-uno', $body);
    }

    private function seedMuseumCatalog(): void
    {
        $this->domainAdapter->fakeGet('public/catalog/categories', [
            ['id' => 1, 'slug' => 'obras', 'name' => 'Obras'],
            ['id' => 2, 'slug' => 'piezas', 'name' => 'Piezas'],
        ]);

        $this->domainAdapter->fakeGet('public/catalog/collection-items', [
            [
                'id' => 101,
                'name' => 'Obra Uno',
                'inventory_code' => 'obra-uno',
                'summary' => 'Resumen de la primera obra.',
                'category_id' => 1,
                'cover_image' => [
                    'url' => 'https://example.com/obra-uno.jpg',
                    'variants' => [],
                ],
                'status' => 'published',
                'is_active' => 1,
                'created_at' => '2026-01-01 10:00:00',
            ],
            [
                'id' => 102,
                'name' => 'Obra Dos',
                'inventory_code' => 'obra-dos',
                'summary' => 'Resumen de la segunda obra.',
                'category_id' => 2,
                'cover_image' => [
                    'url' => 'https://example.com/obra-dos.jpg',
                    'variants' => [],
                ],
                'status' => 'published',
                'is_active' => 1,
                'created_at' => '2026-01-02 10:00:00',
            ],
        ], ['total' => 13, 'page' => 1, 'per_page' => 12]);
    }

    private function seedEventListing(): void
    {
        $this->domainAdapter->fakeGet('public/events', [
            [
                'id' => 201,
                'uuid' => 'evt-201',
                'title' => 'Festival Uno',
                'event_type' => 'festival',
                'description' => 'Descripción del festival uno.',
                'localized' => [
                    'title' => 'Festival Uno',
                    'description' => 'Descripción del festival uno.',
                ],
                'start_time' => '2026-08-01 19:00:00',
                'end_time' => '2026-08-01 21:00:00',
                'venue' => 'Sala Principal',
                'status' => 'published',
                'cover_image' => [
                    'url' => 'https://example.com/festival-uno.jpg',
                    'variants' => [],
                ],
            ],
            [
                'id' => 202,
                'uuid' => 'evt-202',
                'title' => 'Función Dos',
                'event_type' => 'function',
                'description' => 'Descripción de la función dos.',
                'localized' => [
                    'title' => 'Función Dos',
                    'description' => 'Descripción de la función dos.',
                ],
                'start_time' => '2026-08-02 19:00:00',
                'end_time' => '2026-08-02 21:00:00',
                'venue' => 'Sala Secundaria',
                'status' => 'published',
                'cover_image' => [
                    'url' => 'https://example.com/funcion-dos.jpg',
                    'variants' => [],
                ],
            ],
        ], ['total' => 13, 'page' => 1, 'per_page' => 12]);

        $this->domainAdapter->fakeGet('public/events/types', [
            [
                'slug' => 'festival',
                'name' => 'Festival',
                'sort_order' => 20,
                'localized' => ['name' => 'Festival'],
            ],
            [
                'slug' => 'function',
                'name' => 'Función',
                'sort_order' => 10,
                'localized' => ['name' => 'Función'],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function page(string $slug, string $title, array $overrides = []): array
    {
        return array_replace([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => '',
            'meta_description' => '',
            'canonical_url' => '',
            'blocks' => [],
            'localized_slugs' => [
                'es' => $slug,
                'en' => $slug,
            ],
        ], $overrides);
    }

    private function domainPath(string $path): string
    {
        return 'public/' . $this->locale() . '/' . ltrim($path, '/');
    }
}
