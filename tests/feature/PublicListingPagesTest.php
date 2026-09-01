<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

final class PublicListingPagesTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    public function testMuseumListingIsDeliveredByTheBff(): void
    {
        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/museo/coleccion',
            $this->pageEnvelope('museo/coleccion', 'Colección del museo'),
        );

        $result = $this->get('es/museo/coleccion');

        $result->assertStatus(200);
        $result->assertSee('Colección del museo');
        self::assertSame(['public-read/es/page-resolve/museo/coleccion'], $this->domainAdapter->requestedPaths());
    }

    public function testEventListingIsDeliveredByTheBff(): void
    {
        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/cartelera',
            $this->pageEnvelope('cartelera', 'Cartelera'),
        );

        $result = $this->get('es/cartelera');

        $result->assertStatus(200);
        $result->assertSee('Cartelera');
        self::assertSame(['public-read/es/page-resolve/cartelera'], $this->domainAdapter->requestedPaths());
    }

    /** @return array<string, mixed> */
    private function pageEnvelope(string $route, string $title): array
    {
        return [
            'outcome' => 'page',
            'page' => [
                'page_type' => 'cms_page',
                'page_type_key' => $route === 'cartelera' ? 'events' : 'catalog_listing',
                'title' => $title,
                'excerpt' => 'Contenido de listado.',
                'meta_title' => $title,
                'meta_description' => 'Contenido de listado.',
                'slug' => $route,
                'localized_slugs' => ['es' => $route, 'en' => $route],
                'canonical_url' => '/es/' . $route,
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
            'meta' => ['locale' => 'es', 'route' => $route],
            'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            'messages' => [],
        ];
    }
}
