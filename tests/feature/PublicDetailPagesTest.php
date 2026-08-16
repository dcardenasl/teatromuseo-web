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
                context: [
                    'id' => 101,
                    'name' => 'Pieza de prueba',
                    'localized' => ['name' => 'Pieza localizada', 'summary' => 'Resumen localizado.'],
                ],
            ),
        );

        $result = $this->get('es/museo/coleccion/pieza-de-prueba');
        $html = (string) $result->getBody();

        $result->assertStatus(200);
        $result->assertSee('Pieza localizada');
        $result->assertSee('Resumen localizado.');
        self::assertSame(1, preg_match_all('/<h1\b/i', $html));
        self::assertStringNotContainsString('text-4xl font-bold mb-4', $html);
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
                context: [
                    'id' => 201,
                    'title' => 'Festival Uno',
                    'localized' => ['title' => 'Festival Uno', 'description' => 'Descripción del festival uno.'],
                ],
            ),
        );

        $result = $this->get('es/cartelera/festival-uno');
        $html = (string) $result->getBody();

        $result->assertStatus(200);
        $result->assertSee('Festival Uno');
        $result->assertSee('Descripción del festival uno.');
        self::assertSame(1, preg_match_all('/<h1\b/i', $html));
        self::assertStringNotContainsString('text-4xl font-bold mb-4', $html);
        self::assertSame(['public-read/es/page-resolve/cartelera/festival-uno'], $this->domainAdapter->requestedPaths());
        self::assertNotContains('public-read/es/events/festival-uno', $this->domainAdapter->requestedPaths());
        self::assertNotContains('public/es/pages/by-type/template_event_item', $this->domainAdapter->requestedPaths());
    }

    public function testEventDetailLanguageLinksUseLocaleRouteAndLocalizedEntrySlug(): void
    {
        $this->configureLocales(['es', 'en', 'fr', 'pt']);

        $envelope = $this->bffEnvelope(
            route: 'cartelera/gotita-es',
            title: 'Gotita de agua',
            excerpt: 'Descripción de prueba.',
            pageType: 'template_event_item',
            contextKey: 'event_item',
            context: ['id' => 202, 'title' => 'Gotita de agua'],
        );
        $envelope['page']['localized_slugs'] = [
            'es' => 'cartelera/gotita-es',
            'en' => 'programming/little-drop',
            'fr' => 'programming/petite-goutte',
            'pt' => 'programming/gotinha',
        ];
        $envelope['page']['localized_urls'] = [
            'es' => '/es/cartelera/gotita-es',
            'en' => '/en/programming/little-drop',
            'fr' => '/fr/programmation/petite-goutte',
            'pt' => '/pt/programacao/gotinha',
        ];

        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/cartelera/gotita-es',
            $envelope,
        );

        $result = $this->get('es/cartelera/gotita-es');
        $html = (string) $result->getBody();

        $result->assertStatus(200);
        self::assertStringContainsString('/fr/programmation/petite-goutte', $html);
        self::assertStringNotContainsString('/fr/programming/petite-goutte', $html);
    }

    public function testEventDetailLanguageLinksFallbackToCurrentSlugWhenLocaleSlugIsAbsent(): void
    {
        $this->configureLocales(['es', 'en', 'fr', 'pt']);

        $envelope = $this->bffEnvelope(
            route: 'cartelera/gotita-es',
            title: 'Gotita de agua',
            excerpt: 'Descripción de prueba.',
            pageType: 'template_event_item',
            contextKey: 'event_item',
            context: ['id' => 203, 'title' => 'Gotita de agua'],
        );

        $envelope['page']['localized_slugs'] = ['es' => 'cartelera/gotita-es'];
        $envelope['page']['localized_urls'] = [
            'es' => '/es/cartelera/gotita-es',
            'fr' => '/fr/programmation/gotita-es',
        ];

        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/cartelera/gotita-es',
            $envelope,
        );

        $result = $this->get('es/cartelera/gotita-es');
        $html = (string) $result->getBody();

        $result->assertStatus(200);
        self::assertStringContainsString('/fr/programmation/gotita-es', $html);
        self::assertStringNotContainsString('/fr/programming/gotita-es', $html);
    }

    public function testCatalogDetailLanguageLinksUseLocaleRouteAndLocalizedEntrySlug(): void
    {
        $this->configureLocales(['es', 'en', 'fr', 'pt']);

        $envelope = $this->bffEnvelope(
            route: 'museo/coleccion/pieza-es',
            title: 'Pieza localizada',
            excerpt: 'Resumen de prueba.',
            pageType: 'template_catalog_item',
            contextKey: 'catalog_item',
            context: ['id' => 204, 'name' => 'Pieza localizada'],
        );
        $envelope['page']['localized_slugs'] = [
            'es' => 'museo/coleccion/pieza-es',
            'en' => 'museum/collection/piece-en',
            'fr' => 'museum/collection/piece-fr',
            'pt' => 'museum/collection/peca-pt',
        ];
        $envelope['page']['localized_urls'] = [
            'es' => '/es/museo/coleccion/pieza-es',
            'en' => '/en/museum/collection/piece-en',
            'fr' => '/fr/musee/collection/piece-fr',
            'pt' => '/pt/museu/colecao/peca-pt',
        ];

        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/museo/coleccion/pieza-es',
            $envelope,
        );

        $result = $this->get('es/museo/coleccion/pieza-es');
        $html = (string) $result->getBody();

        $result->assertStatus(200);
        self::assertStringContainsString('/fr/musee/collection/piece-fr', $html);
        self::assertStringNotContainsString('/fr/museum/collection/piece-fr', $html);
    }

    public function testDetailUsesCmsSeoPolicyAndCanonicalizesTheResolvedDomainUrl(): void
    {
        $envelope = $this->bffEnvelope(
            route: 'cartelera/evento-seo',
            title: 'Evento SEO',
            excerpt: 'Descripción del evento SEO.',
            pageType: 'template_event_item',
            contextKey: 'event_item',
            context: ['id' => 205, 'title' => 'Evento SEO'],
        );
        $envelope['page']['robots'] = 'noindex, nofollow';
        $envelope['page']['og_type'] = 'article';
        $envelope['page']['og_image'] = ['url' => '/uploads/event-share.webp'];
        $envelope['page']['schema_data'] = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => 'Evento SEO',
        ];
        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/cartelera/evento-seo',
            $envelope,
        );

        $result = $this->get('es/cartelera/evento-seo');
        $html = (string) $result->getBody();

        $result->assertStatus(200);
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        self::assertStringContainsString('<meta property="og:type" content="article">', $html);
        self::assertStringContainsString('<meta property="og:image" content="' . site_url('/uploads/event-share.webp') . '">', $html);
        self::assertStringContainsString(
            '<link rel="canonical" href="' . site_url('/es/cartelera/evento-seo') . '">',
            $html,
        );
        self::assertStringContainsString('"@type":"Event"', $html);
        self::assertSame(1, preg_match_all('/<h1\b/i', $html));
    }

    public function testDetailSeoDoesNotInventHreflangUrlsForUndeclaredLocales(): void
    {
        $this->configureLocales(['es', 'en', 'fr', 'pt']);
        $envelope = $this->bffEnvelope(
            route: 'cartelera/evento-es',
            title: 'Evento localizado',
            excerpt: 'Descripción localizada.',
            pageType: 'template_event_item',
            contextKey: 'event_item',
            context: ['id' => 206, 'title' => 'Evento localizado'],
        );
        $envelope['page']['localized_urls'] = [
            'es' => '/es/cartelera/evento-es',
            'fr' => '/fr/programmation/evenement-fr',
        ];

        $this->domainAdapter->fakeGet(
            'public-read/es/page-resolve/cartelera/evento-es',
            $envelope,
        );

        $result = $this->get('es/cartelera/evento-es');
        $html = (string) $result->getBody();

        $result->assertStatus(200);
        self::assertStringContainsString('hreflang="es"', $html);
        self::assertStringContainsString('hreflang="fr"', $html);
        self::assertStringNotContainsString('hreflang="en"', $html);
        self::assertStringNotContainsString('hreflang="pt"', $html);
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
                'showPageHeading' => false,
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
