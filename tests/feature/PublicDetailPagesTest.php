<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

final class PublicDetailPagesTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    public function testMuseumDetailUsesOneBffRequestAndItsSeededContext(): void
    {
        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/museo/coleccion/pieza-de-prueba',
            $this->bffEnvelope(
                route: 'museo/coleccion/pieza-de-prueba',
                title: 'Pieza localizada',
                excerpt: 'Resumen localizado.',
                pageType: 'template_catalog_item',
                contextKey: 'catalog_item',
                context: ['id' => 101, 'name' => 'Pieza de prueba'],
            ),
        );

        $result = $this->get('es/museo/coleccion/pieza-de-prueba');

        $result->assertStatus(200);
        $result->assertSee('Pieza localizada');
        $result->assertSee('Resumen localizado.');
        self::assertSame(['public-read/es/page-resolve/museo/coleccion/pieza-de-prueba'], $this->domainAdapter->requestedPaths());
        self::assertNotContains('public-read/es/collection-items/pieza-de-prueba', $this->domainAdapter->requestedPaths());
        self::assertNotContains('public/es/pages/by-type/template_catalog_item', $this->domainAdapter->requestedPaths());
    }

    public function testEventDetailUsesOneBffRequestAndItsSeededContext(): void
    {
        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/cartelera/festival-uno',
            $this->bffEnvelope(
                route: 'cartelera/festival-uno',
                title: 'Festival Uno',
                excerpt: 'Descripción del festival uno.',
                pageType: 'template_event_item',
                contextKey: 'event_item',
                context: ['id' => 201, 'title' => 'Festival Uno'],
            ),
        );

        $result = $this->get('es/cartelera/festival-uno');

        $result->assertStatus(200);
        $result->assertSee('Festival Uno');
        $result->assertSee('Descripción del festival uno.');
        self::assertSame(['public-read/es/page-resolve/cartelera/festival-uno'], $this->domainAdapter->requestedPaths());
        self::assertNotContains('public-read/es/events/festival-uno', $this->domainAdapter->requestedPaths());
        self::assertNotContains('public/es/pages/by-type/template_event_item', $this->domainAdapter->requestedPaths());
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function bffEnvelope(
        string $route,
        string $title,
        string $excerpt,
        string $pageType,
        string $contextKey,
        array $context,
    ): array {
        return [
            'outcome' => 'page',
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
                'blocks' => [['block_key' => $pageType === 'template_event_item' ? 'event_item_header' : 'catalog_item_header']],
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
}
