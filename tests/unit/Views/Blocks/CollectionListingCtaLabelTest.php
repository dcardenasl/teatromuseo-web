<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * COL-002: the entry CTA label ("Ver noticia" / "Ver evento" / ...) must come from the
 * collection's own editable `entry_cta_label` translation when set, and only fall back to a
 * collection_type-based default (news/portfolio) or a generic label for anything else.
 *
 * @internal
 */
final class CollectionListingCtaLabelTest extends CIUnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function baseVars(array $collectionOverrides): array
    {
        return [
            'isValid' => true,
            'collection' => array_replace([
                'id' => 1,
                'collection_key' => 'demo',
                'collection_type' => 'other',
                'name' => 'Demo',
            ], $collectionOverrides),
            'collectionKey' => 'demo',
            'collectionUrlPath' => 'demo',
            'localizedUrls' => [],
            'entries' => [
                ['title' => 'Entry One', 'slug' => 'entry-one'],
            ],
            'pagination' => ['total_pages' => 1, 'per_page' => 12, 'current_page' => 1],
            'currentPage' => 1,
            'currentCategory' => '',
            'currentTag' => '',
            'currentQuery' => '',
            'orderBy' => 'published_at',
            'orderDirection' => 'desc',
            'layoutVariant' => 'cards',
            'imageAspectRatio' => '16/9',
            'cssClass' => '',
            'showSearch' => false,
            'showCategories' => false,
            'showTags' => false,
            'showExcerpt' => false,
            'showDate' => false,
            'showButton' => true,
            'showItemCategories' => false,
            'showExtraRichtext' => false,
            'showExtraLink' => false,
            'showExtraImage' => false,
            'emptyMessage' => '',
            'introTitle' => '',
            'introText' => '',
            'categories' => [],
            'tags' => [],
            'pageTitle' => 'Demo',
            'metaDescription' => '',
            'lang' => 'es',
        ];
    }

    public function testCustomEntryCtaLabelTakesPriorityOverCollectionType(): void
    {
        $html = view('blocks/collection_listing', $this->baseVars([
            'collection_type' => 'news',
            'entry_cta_label' => 'Ver evento',
        ]));

        $this->assertStringContainsString('Ver evento', $html);
        $this->assertStringNotContainsString('Leer artículo', $html);
    }

    public function testNewsCollectionTypeFallsBackToArticleLabelWhenCtaLabelIsEmpty(): void
    {
        $html = view('blocks/collection_listing', $this->baseVars([
            'collection_type' => 'news',
            'entry_cta_label' => null,
        ]));

        $this->assertStringContainsString('Leer artículo', $html);
    }

    public function testPortfolioCollectionTypeFallsBackToProjectLabelWhenCtaLabelIsEmpty(): void
    {
        $html = view('blocks/collection_listing', $this->baseVars([
            'collection_type' => 'portfolio',
            'entry_cta_label' => '',
        ]));

        $this->assertStringContainsString('Ver proyecto', $html);
    }

    public function testCustomCollectionTypeFallsBackToGenericLabelWhenCtaLabelIsEmpty(): void
    {
        $html = view('blocks/collection_listing', $this->baseVars([
            'collection_type' => 'eventos',
        ]));

        $this->assertStringContainsString('Ver más', $html);
        $this->assertStringNotContainsString('Ver proyecto', $html);
        $this->assertStringNotContainsString('Leer artículo', $html);
    }

    public function testListingCardsUseAnOptimizedVariantForAbsoluteImageUrls(): void
    {
        $vars = $this->baseVars([
            'collection_type' => 'catalog',
        ]);
        $vars['entries'][0]['featured_image'] = [
            'url' => 'https://cdn.example.com/cover-original.jpg',
            'variants' => [
                'sm' => ['url' => 'https://cdn.example.com/cover_sm.webp', 'width' => 400, 'height' => 300],
            ],
        ];

        $html = view('blocks/collection_listing', $vars);

        $this->assertStringContainsString('src="https://cdn.example.com/cover_sm.webp"', $html);
        $this->assertStringNotContainsString('src="https://cdn.example.com/cover-original.jpg"', $html);
    }

    public function testListingVideoUsesItsYouTubePosterAndModalTrigger(): void
    {
        $vars = $this->baseVars(['collection_type' => 'video']);
        $vars['entries'][0] = [
            'title' => 'Video de prueba',
            'featured_image' => null,
            'listing_content' => [
                'video' => [
                    'provider' => 'youtube',
                    'id' => 'dQw4w9WgXcQ',
                    'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
                    'poster_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                ],
            ],
        ];

        $html = view('blocks/collection_listing', $vars);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('data-video-listing', $html);
        $this->assertStringContainsString('data-video-trigger', $html);
        $this->assertStringContainsString('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $html);
        $this->assertStringContainsString('Reproducir: Video de prueba', $html);
    }

    public function testListingVimeoWithoutPosterStillRendersItsModalTrigger(): void
    {
        $vars = $this->baseVars(['collection_type' => 'video']);
        $vars['entries'][0] = [
            'title' => 'Vimeo de prueba',
            'featured_image' => null,
            'listing_content' => [
                'video' => [
                    'provider' => 'vimeo',
                    'id' => '12345678',
                    'embed_url' => 'https://player.vimeo.com/video/12345678?autoplay=1',
                    'poster_url' => '',
                ],
            ],
        ];

        $html = html_entity_decode(view('blocks/collection_listing', $vars), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('data-video-trigger', $html);
        $this->assertStringContainsString('https://player.vimeo.com/video/12345678?autoplay=1', $html);
        $this->assertStringContainsString('bg-slate-900', $html);
    }
}
