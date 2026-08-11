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
}
