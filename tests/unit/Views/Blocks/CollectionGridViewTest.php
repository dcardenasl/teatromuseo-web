<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CollectionGridViewTest extends CIUnitTestCase
{
    public function testCollectionGridRendersViewAllAndEntryLinks(): void
    {
        $html = view('blocks/collection_grid', [
            'sectionTitle' => 'Noticias',
            'sectionSubtitle' => '',
            'viewAllLabel' => 'Ver todos',
            'emptyMessage' => '',
            'collectionKey' => 'noticias',
            'layoutVariant' => 'cards',
            'imageAspectRatio' => '1/1',
            'imageAspectRatioClass' => 'aspect-square',
            'cssClass' => '',
            'canonicalViewAllUrl' => '/es/noticias',
            'entries' => [[
                'title' => 'Entrada de prueba',
                'excerpt' => '',
                'published_at' => '',
                'navigation' => ['url' => '/es/noticias/entrada-de-prueba'],
                'featured_image' => null,
            ]],
            'sectionClass' => 'section',
            'containerClass' => 'container-base',
            'gridClass' => 'grid gap-6 md:grid-cols-3',
            'lang' => 'es',
        ], ['saveData' => false]);

        $this->assertStringContainsString('Ver todos', $html);
        $this->assertStringContainsString('/es/noticias"', $html);
        $this->assertStringContainsString('href="/es/noticias/entrada-de-prueba"', $html);
    }

    public function testCollectionGridHonorsConfiguredAspectRatio(): void
    {
        $html = view('blocks/collection_grid', [
            'sectionTitle' => 'Noticias',
            'sectionSubtitle' => '',
            'viewAllLabel' => '',
            'emptyMessage' => '',
            'collectionKey' => 'noticias',
            'layoutVariant' => 'cards',
            'imageAspectRatio' => '3/4',
            'imageAspectRatioClass' => 'aspect-[3/4]',
            'cssClass' => '',
            'canonicalViewAllUrl' => '/noticias',
            'entries' => [[
                'title' => 'Entrada de prueba',
                'excerpt' => 'Resumen de prueba',
                'published_at' => '2026-08-01 10:00:00',
                'slug' => 'entrada-de-prueba',
                'featured_image' => [
                    'url' => 'https://example.com/entrada.jpg',
                    'variants' => [
                        'sd' => ['url' => 'https://example.com/entrada_sd.webp', 'width' => 640, 'height' => 480],
                        'md' => ['url' => 'https://example.com/entrada_md.webp', 'width' => 750, 'height' => 1000],
                    ],
                ],
            ]],
            'sectionClass' => 'section',
            'containerClass' => 'container-base',
            'gridClass' => 'grid gap-6 md:grid-cols-3',
            'lang' => 'es',
        ], ['saveData' => false]);

        $this->assertStringContainsString('aspect-[3/4]', $html);
        $this->assertStringNotContainsString('aspect-video', $html);
        $this->assertStringContainsString('src="https://example.com/entrada_sd.webp"', $html);
        $this->assertStringNotContainsString('entrada_md.webp', $html);
        $this->assertStringNotContainsString('src="https://example.com/entrada.jpg"', $html);
    }

    public function testCollectionGridRendersYouTubePosterAndVideoTrigger(): void
    {
        $html = view('blocks/collection_grid', [
            'sectionTitle' => 'Videos',
            'sectionSubtitle' => '',
            'viewAllLabel' => '',
            'emptyMessage' => '',
            'collectionKey' => 'videos',
            'layoutVariant' => 'cards',
            'imageAspectRatio' => '16/9',
            'imageAspectRatioClass' => 'aspect-video',
            'cssClass' => '',
            'canonicalViewAllUrl' => '',
            'entries' => [[
                'title' => 'Video de prueba',
                'excerpt' => '',
                'published_at' => '',
                'navigation' => ['url' => '/es/videos/video-de-prueba'],
                'featured_image' => null,
                'listing_content' => [
                    'video' => [
                        'provider' => 'youtube',
                        'id' => 'dQw4w9WgXcQ',
                        'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
                        'poster_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                    ],
                ],
            ]],
            'sectionClass' => 'section',
            'containerClass' => 'container-base',
            'gridClass' => 'grid gap-6 md:grid-cols-3',
            'lang' => 'es',
        ], ['saveData' => false]);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('data-video-listing', $html);
        $this->assertStringContainsString('data-video-trigger', $html);
        $this->assertStringContainsString('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $html);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1', $html);
        $this->assertStringContainsString('Reproducir: Video de prueba', $html);
    }
}
