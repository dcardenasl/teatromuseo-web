<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\CollectionGridViewModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * The view model consumes only the path-keyed listing envelope assembled by
 * PageDelivery/BlockRenderer. These tests use that same boundary directly;
 * no domain service is available during rendering.
 *
 * @internal
 */
final class CollectionGridViewModelTest extends CIUnitTestCase
{
    /**
     * @param list<array<string, mixed>>  $collections
     * @param array<string, mixed>        $entriesResult
     * @return array<string, mixed>
     */
    private function context(
        array $collections,
        array $entriesResult,
        string $path = '/',
        ?array $eventsResult = null,
    ): array {
        $request = new IncomingRequest(config(App::class), new URI('http://localhost/' . ltrim($path, '/')), null, new UserAgent());
        $request->setLocale('es');

        $listing = [
            'ok' => true,
            'status' => 200,
            'data' => $eventsResult['data'] ?? $entriesResult['data'] ?? [],
            'meta' => $eventsResult['meta'] ?? $entriesResult['meta'] ?? [],
            'collection' => $collections[0] ?? null,
        ];

        return [
            'request' => $request,
            'blockPath' => '0',
            'block_prefetch_complete' => true,
            'block_prefetch' => ['0' => $listing],
        ];
    }

    public function testResolvesCanonicalUrlAndEntries(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => ['collection_key' => 'news'],
            'block_data'   => ['section_title' => 'Noticias'],
            'navigation'   => [
                'status' => 'resolved',
                'target_type' => 'collection_index',
                'target_id' => 1,
                'url' => '/es/news',
                'label' => 'Ver todas las noticias',
            ],
        ], 'es', $this->context(
            [[
                'collection_key' => 'news',
                'slug'           => 'noticias',
                'url_path'       => '/noticias',
                'index_page'     => [
                    'localized_slugs' => ['es' => 'noticias', 'en' => 'news'],
                ],
            ]],
            ['data' => [[
                'id' => 9,
                'title' => 'Post 1',
                'slug' => 'post-1',
                'localized' => ['slug' => 'noticia-localizada'],
                'featured_image' => [
                    'source_kind' => 'external_url',
                    'url' => 'https://cdn.example.com/post-1.jpg',
                ],
            ]], 'meta' => []]
        ));

        $vars = $vm->vars();

        $this->assertSame('news', $vars['collectionKey']);
        $this->assertCount(1, $vars['entries']);
        $this->assertSame('https://cdn.example.com/post-1.jpg', $vars['entries'][0]['featured_image']['url']);
        $this->assertSame('Ver todas las noticias', $vars['viewAllLabel']);
        $this->assertSame('/es/news/noticia-localizada', $vars['entries'][0]['navigation']['url']);
        $this->assertNotSame('', $vars['canonicalViewAllUrl']);
    }

    public function testGridKeepsAValidYouTubeVideoForCardPresentation(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => ['collection_key' => 'videos'],
        ], 'es', $this->context(
            [['collection_key' => 'videos', 'slug' => 'videos']],
            ['data' => [[
                'id' => 10,
                'title' => 'Video de prueba',
                'slug' => 'video-de-prueba',
                'listing_content' => [
                    'video' => [
                        'provider' => 'youtube',
                        'id' => 'dQw4w9WgXcQ',
                    ],
                ],
            ]], 'meta' => []],
            '/',
        ));

        $vars = $vm->vars();

        $this->assertSame('youtube', $vars['entries'][0]['listing_content']['video']['provider']);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $vars['entries'][0]['listing_content']['video']['poster_url']);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $vars['entries'][0]['listing_content']['video']['embed_url']);
    }

    public function testInvalidConfigFallsBackToSafeDefaults(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => [
                'collection_key'  => 'news',
                'order_by'        => 'DROP TABLE',
                'order_direction' => 'sideways',
                'layout_variant'  => 'bogus',
                'items_limit'     => 5000,
            ],
            'block_data' => [],
        ], 'es', $this->context([], ['data' => [], 'meta' => []]));

        $vars = $vm->vars();

        $this->assertSame('cards', $vars['layoutVariant']);
        $this->assertSame('1/1', $vars['imageAspectRatio']);
        $this->assertSame('aspect-square', $vars['imageAspectRatioClass']);
        $this->assertSame('/es/news', parse_url($vars['canonicalViewAllUrl'], PHP_URL_PATH));
        $this->assertSame(lang('Site.view_all'), $vars['viewAllLabel']);
        $this->assertStringContainsString('md:grid-cols-3', $vars['gridClass']);
    }

    public function testEventCardsUseLocalizedSlugAndResolvedListingNavigation(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => [
                'collection_key' => 'cartelera',
                'source_type' => 'auto',
            ],
            'block_data' => [
                'section_title' => 'Cartelera',
                'view_all_label' => 'Ver toda la cartelera',
            ],
            'navigation' => [
                'status' => 'resolved',
                'target_type' => 'events',
                'target_id' => 26,
                'url' => '/es/cartelera',
                'label' => 'Ver toda la cartelera',
            ],
        ], 'es', $this->context(
            [],
            ['data' => [], 'meta' => []],
            '/',
            ['data' => [[
                'id' => 388,
                'localized' => [
                    'locale' => 'es',
                    'title' => 'Tupuna, rostros vivos',
                    'slug' => 'tupuna-rostros-vivos',
                ],
                'occurrences' => [[
                    'start_time' => '2026-07-26 16:30:00',
                    'end_time' => '2026-07-26 18:30:00',
                    'timezone' => 'America/Santiago',
                ]],
            ]], 'meta' => ['pagination' => ['total' => 1]]]
        ));

        $vars = $vm->vars();

        $this->assertSame('Ver toda la cartelera', $vars['viewAllLabel']);
        $this->assertSame('/es/cartelera', $vars['canonicalViewAllUrl']);
        $this->assertSame('/es/cartelera/tupuna-rostros-vivos', $vars['entries'][0]['navigation']['url']);
    }

    public function testExplicitAspectRatioIsRespected(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => [
                'collection_key' => 'news',
                'image_aspect_ratio' => '3/4',
            ],
        ], 'es', $this->context([], ['data' => [], 'meta' => []]));

        $vars = $vm->vars();

        $this->assertSame('3/4', $vars['imageAspectRatio']);
        $this->assertSame('aspect-[3/4]', $vars['imageAspectRatioClass']);
    }

    public function testCollectionKeyInfersAppropriateFallbackAspectRatio(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => [
                'collection_key' => 'cartelera',
            ],
        ], 'es', $this->context([], ['data' => [], 'meta' => []]));

        $vars = $vm->vars();

        $this->assertSame('1/1', $vars['imageAspectRatio']);
        $this->assertSame('aspect-square', $vars['imageAspectRatioClass']);
    }

    public function testCoursesInferPortraitAspectRatio(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => [
                'collection_key' => 'cursos',
            ],
        ], 'es', $this->context([], ['data' => [], 'meta' => []]));

        $vars = $vm->vars();

        $this->assertSame('3/4', $vars['imageAspectRatio']);
        $this->assertSame('aspect-[3/4]', $vars['imageAspectRatioClass']);
    }

    public function testNewsInferSquareAspectRatioWhenNoExplicitRatioIsSet(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => [
                'collection_key' => 'noticias',
            ],
        ], 'es', $this->context([], ['data' => [], 'meta' => []]));

        $vars = $vm->vars();

        $this->assertSame('1/1', $vars['imageAspectRatio']);
        $this->assertSame('aspect-square', $vars['imageAspectRatioClass']);
    }

    public function testEmptyCollectionKeySkipsServiceCalls(): void
    {
        // No context passed on purpose: with an empty key no service is touched.
        $vm = new CollectionGridViewModel([], 'es');

        $vars = $vm->vars();

        $this->assertSame('', $vars['collectionKey']);
        $this->assertSame([], $vars['entries']);
        $this->assertSame('', $vars['canonicalViewAllUrl']);
    }

    public function testPortfolioVariantChangesLayoutClasses(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => ['collection_key' => 'work', 'layout_variant' => 'portfolio'],
        ], 'es', $this->context([], ['data' => [], 'meta' => []]));

        $vars = $vm->vars();

        $this->assertStringContainsString('bg-slate-50/50', $vars['sectionClass']);
        $this->assertStringContainsString('lg:grid-cols-3', $vars['gridClass']);
    }

    public function testMissingContextCollaboratorsProduceSafeDefaultsInsteadOfErrors(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => ['collection_key' => 'news'],
        ], 'es', []);

        $vars = $vm->vars();

        $this->assertSame('news', $vars['collectionKey']);
        $this->assertSame([], $vars['entries']);
        $this->assertSame('/es/news', parse_url($vars['canonicalViewAllUrl'], PHP_URL_PATH));
        $this->assertSame('1/1', $vars['imageAspectRatio']);
    }
}
