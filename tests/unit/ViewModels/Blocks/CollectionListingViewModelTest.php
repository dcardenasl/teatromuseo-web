<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\Services\SiteCategoryService;
use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;
use App\Services\SiteTagService;
use App\ViewModels\Blocks\CollectionListingViewModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * Characterization tests for CollectionListingViewModel — this view model had
 * zero direct unit coverage before (only a view-rendering test with a
 * hand-built vars array). Rewritten for DEEP-WEB-02: the view model no longer
 * calls `service()`/`Config\Services::x()` itself, so tests construct it with
 * an explicit `$context` array (the same collaborators BlockRenderer resolves
 * in production) instead of mutating global service state.
 *
 * @internal
 */
final class CollectionListingViewModelTest extends CIUnitTestCase
{
    private const COLLECTION = [
        'id' => 1,
        'collection_key' => 'news',
        'slug' => 'noticias',
        'listing_title' => 'Noticias',
        'name' => 'Noticias',
        'default_meta_description' => 'Últimas noticias.',
        'url_path' => '/noticias',
        'index_page' => ['localized_slugs' => ['es' => 'noticias', 'en' => 'news']],
    ];

    /**
     * @param list<array<string, mixed>> $collections
     * @param array<string, mixed> $entriesResult
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $tags
     * @param array<string, string> $get
     * @return array<string, mixed>
     */
    private function context(
        array $collections,
        array $entriesResult,
        array $categories = [],
        array $tags = [],
        array $get = [],
        string $path = '/'
    ): array {
        $collectionService = $this->createMock(SiteCollectionService::class);
        $collectionService->method('getAll')->willReturn($collections);

        $entryService = $this->createMock(SiteEntryService::class);
        $entryService->method('list')->willReturn($entriesResult);

        $categoryService = $this->createMock(SiteCategoryService::class);
        $categoryService->method('list')->willReturn($categories);

        $tagService = $this->createMock(SiteTagService::class);
        $tagService->method('list')->willReturn($tags);

        $request = new IncomingRequest(config(App::class), new URI('http://localhost/' . ltrim($path, '/')), null, new UserAgent());
        $request->setGlobal('get', $get);
        $request->setLocale('es');

        return [
            'request' => $request,
            'siteCollectionService' => $collectionService,
            'siteEntryService' => $entryService,
            'siteCategoryService' => $categoryService,
            'siteTagService' => $tagService,
        ];
    }

    public function testUnresolvableCollectionReturnsInvalidDefaults(): void
    {
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 0]],
            'es',
            $this->context([], ['data' => [], 'meta' => []])
        );

        $vars = $vm->vars();

        $this->assertFalse($vars['isValid']);
        $this->assertSame([], $vars['entries']);
        $this->assertSame('cards', $vars['layoutVariant']);
        $this->assertNull($vars['collection']);
    }

    public function testResolvedCollectionListsEntriesWithDefaultOrdering(): void
    {
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            $this->context(
                [self::COLLECTION],
                ['data' => [[
                    'title' => 'Post 1',
                    'slug' => 'post-1',
                    'featured_image' => [
                        'source_kind' => 'external_url',
                        'url' => 'https://cdn.example.com/post-1.jpg',
                    ],
                ]], 'meta' => ['pagination' => ['total' => 1]]]
            )
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['isValid']);
        $this->assertSame('news', $vars['collectionKey']);
        $this->assertCount(1, $vars['entries']);
        $this->assertSame('https://cdn.example.com/post-1.jpg', $vars['entries'][0]['featured_image']['url']);
        $this->assertSame('published_at', $vars['orderBy']);
        $this->assertSame('desc', $vars['orderDirection']);
        $this->assertSame(1, $vars['currentPage']);
        $this->assertSame('Noticias', $vars['pageTitle']);
        $this->assertSame('Últimas noticias.', $vars['metaDescription']);
    }

    public function testFallbackImageUrlIsNeverUsedEvenWhenConfigured(): void
    {
        // block_config can carry an admin-authored placeholder photo (a leftover from the
        // template catalog's demo content) — it must never be surfaced, even when explicitly
        // set, so every card without a real cover just shows no image instead of the same
        // generic stock photo repeated across the grid.
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1, 'fallback_image_url' => 'https://images.unsplash.com/photo-placeholder']],
            'es',
            $this->context(
                [self::COLLECTION],
                ['data' => [], 'meta' => ['pagination' => ['total' => 0]]]
            )
        );

        $vars = $vm->vars();

        $this->assertSame('', $vars['fallbackImageUrl']);
    }

    public function testResolvedCollectionFallsBackToNameWhenListingTitleIsEmpty(): void
    {
        $collection = self::COLLECTION;
        $collection['listing_title'] = '';
        $collection['name'] = 'Festivales';

        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            $this->context(
                [$collection],
                ['data' => [['title' => 'Post 1', 'slug' => 'post-1']], 'meta' => ['pagination' => ['total' => 1]]]
            )
        );

        $vars = $vm->vars();

        $this->assertSame('Festivales', $vars['pageTitle']);
    }

    public function testCollectionPathFallsBackToCollectionKeyWhenIndexPageIsMissing(): void
    {
        $collection = self::COLLECTION;
        unset($collection['index_page']);

        // The listing block is embedded on an unrelated page (path below) —
        // the resolved URL must still be derived from the collection itself
        // (`collection_key`), not from whatever page happens to host the
        // block. Otherwise the same entry would resolve to a different URL
        // depending on which page rendered it, and entries embedded on the
        // homepage (empty path beyond the locale) would get no working link
        // at all.
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            $this->context(
                [$collection],
                ['data' => [['title' => 'Post 1', 'slug' => 'post-1']], 'meta' => ['pagination' => ['total' => 1]]],
                [],
                [],
                [],
                '/es/festivales'
            )
        );

        $vars = $vm->vars();

        $this->assertSame('/news', $vars['collectionUrlPath']);
    }

    public function testCollectionPathFallsBackToCollectionKeyWhenEmbeddedOnHomepage(): void
    {
        $collection = self::COLLECTION;
        unset($collection['index_page']);

        // Regression test: embedding the block on the homepage means the
        // request path is just the locale prefix (nothing left after
        // stripping it) — the old "fall back to the current page path"
        // strategy resolved to an empty base path there, turning every
        // entry link into a dead `href="#"`.
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            $this->context(
                [$collection],
                ['data' => [['title' => 'Post 1', 'slug' => 'post-1']], 'meta' => ['pagination' => ['total' => 1]]],
                [],
                [],
                [],
                '/es'
            )
        );

        $vars = $vm->vars();

        $this->assertSame('/news', $vars['collectionUrlPath']);
    }

    public function testListVariantNormalizesListingContentAndVisibilityFlags(): void
    {
        $vm = new CollectionListingViewModel(
            ['block_config' => [
                'collection_id' => 1,
                'layout_variant' => 'list',
                'show_extra_richtext' => true,
                'show_extra_link' => true,
                'show_extra_image' => true,
            ]],
            'es',
            $this->context([self::COLLECTION], [
                'data' => [[
                    'id' => 1,
                    'listing_content' => [
                        'rich_text' => '<script>alert(1)</script><p>Seguro</p>',
                        'image' => ['url' => '/uploads/extra.jpg', 'alt' => 'Extra'],
                        'secondary_action' => ['label' => 'Más información', 'url' => '/detalle'],
                    ],
                ]],
                'meta' => [],
            ])
        );

        $vars = $vm->vars();

        $this->assertSame('list', $vars['layoutVariant']);
        $this->assertTrue($vars['showExtraRichtext']);
        $this->assertStringNotContainsString('<script>', $vars['entries'][0]['listing_content']['rich_text']);
        $this->assertSame('/uploads/extra.jpg', $vars['entries'][0]['listing_content']['image']['url']);
        $this->assertStringContainsString('/es/detalle', $vars['entries'][0]['listing_content']['secondary_action']['url']);
    }

    public function testInvalidOrderAndDirectionFallBackToSafeDefaults(): void
    {
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            $this->context(
                [self::COLLECTION],
                ['data' => [], 'meta' => []],
                [],
                [],
                ['order_by' => 'DROP TABLE', 'order_direction' => 'sideways']
            )
        );

        $vars = $vm->vars();

        $this->assertSame('published_at', $vars['orderBy']);
        $this->assertSame('desc', $vars['orderDirection']);
    }

    public function testGetParamsDriveCurrentPageCategoryTagAndQuery(): void
    {
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            $this->context(
                [self::COLLECTION],
                ['data' => [], 'meta' => []],
                [],
                [],
                [
                    'page' => '3',
                    'category' => 'destacadas',
                    'tag' => 'promo',
                    'q' => 'hola',
                    'order_by' => 'title',
                    'order_direction' => 'asc',
                ]
            )
        );

        $vars = $vm->vars();

        $this->assertSame(3, $vars['currentPage']);
        $this->assertSame('destacadas', $vars['currentCategory']);
        $this->assertSame('promo', $vars['currentTag']);
        $this->assertSame('hola', $vars['currentQuery']);
        $this->assertSame('title', $vars['orderBy']);
        $this->assertSame('asc', $vars['orderDirection']);
    }

    public function testCategoriesAreResolvedWhenShowCategoriesIsEnabled(): void
    {
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1, 'show_categories' => true]],
            'es',
            $this->context(
                [self::COLLECTION],
                ['data' => [], 'meta' => []],
                [['id' => 1, 'slug' => 'destacadas', 'name' => 'Destacadas']]
            )
        );

        $vars = $vm->vars();

        $this->assertCount(1, $vars['categories']);
        $this->assertArrayHasKey('url', $vars['categories'][0]);
    }

    public function testCategoriesAreSkippedWhenShowCategoriesIsDisabled(): void
    {
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1, 'show_categories' => false]],
            'es',
            $this->context(
                [self::COLLECTION],
                ['data' => [], 'meta' => []],
                [['id' => 1, 'slug' => 'destacadas', 'name' => 'Destacadas']]
            )
        );

        $vars = $vm->vars();

        $this->assertSame([], $vars['categories']);
    }

    public function testTagsAreResolvedOnlyWhenShowTagsIsEnabled(): void
    {
        $context = $this->context(
            [self::COLLECTION],
            ['data' => [], 'meta' => []],
            [],
            [['id' => 1, 'slug' => 'promo', 'name' => 'Promo']]
        );

        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1, 'show_tags' => true]],
            'es',
            $context
        );
        $vars = $vm->vars();

        $this->assertCount(1, $vars['tags']);

        $vmDefault = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            $context
        );
        $this->assertSame([], $vmDefault->vars()['tags'], 'show_tags defaults to false');
    }

    public function testPreviewRouteFabricatesAMockCollectionWhenUnresolvable(): void
    {
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 999]],
            'es',
            $this->context([], ['data' => [], 'meta' => []], [], [], [], 'cms/pages/1/blocks/preview')
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['isValid']);
        $this->assertSame('mock-collection', $vars['collection']['collection_key']);
    }

    public function testImageAspectRatioDefaultsTo16By9AndHonorsExplicitConfig(): void
    {
        $context = $this->context([self::COLLECTION], ['data' => [], 'meta' => []]);

        $vmDefault = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            $context
        );
        $this->assertSame('16/9', $vmDefault->vars()['imageAspectRatio']);

        $vmSquare = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1, 'image_aspect_ratio' => '1/1']],
            'es',
            $context
        );
        $this->assertSame('1/1', $vmSquare->vars()['imageAspectRatio']);
    }

    public function testMissingContextCollaboratorsProduceSafeDefaultsInsteadOfErrors(): void
    {
        // No request, no services in context — plain array construction must
        // not throw, matching how a unit test would build this view model
        // without going through BlockRenderer at all.
        $vm = new CollectionListingViewModel(
            ['block_config' => ['collection_id' => 1]],
            'es',
            []
        );

        $vars = $vm->vars();

        $this->assertFalse($vars['isValid']);
    }
}
