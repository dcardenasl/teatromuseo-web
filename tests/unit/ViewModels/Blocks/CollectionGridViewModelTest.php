<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;
use App\Services\SiteEventService;
use App\ViewModels\Blocks\CollectionGridViewModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * DEEP-WEB-02: the view model no longer calls service()/Config\Services::x()
 * itself, so tests construct it with an explicit $context array (the same
 * collaborators BlockRenderer resolves in production) instead of mutating
 * global service state via Services::injectMock().
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
        ?array &$capturedQuery = null,
    ): array {
        $collectionService = $this->createMock(SiteCollectionService::class);
        $collectionService->method('getAll')->willReturn($collections);

        $entryService = $this->createMock(SiteEntryService::class);
        $entryService->method('list')->willReturnCallback(
            static function (string $lang, string $collectionKey, array $query) use (&$capturedQuery, $entriesResult): array {
                $capturedQuery = $query;

                return $entriesResult;
            }
        );

        $request = new IncomingRequest(config(App::class), new URI('http://localhost/' . ltrim($path, '/')), null, new UserAgent());
        $request->setLocale('es');

        $context = [
            'request' => $request,
            'siteCollectionService' => $collectionService,
            'siteEntryService' => $entryService,
        ];

        if ($eventsResult !== null) {
            $eventService = $this->createMock(SiteEventService::class);
            $eventService->method('listEvents')->willReturn($eventsResult);
            $context['siteEventService'] = $eventService;
        }

        return $context;
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
        $this->assertSame('', $vars['canonicalViewAllUrl']);
        $this->assertStringContainsString('md:grid-cols-3', $vars['gridClass']);
    }

    public function testProjectionOrderDirectionIsUsedForCmsCollectionQueries(): void
    {
        $capturedQuery = null;
        $vm = new CollectionGridViewModel([
            'block_config' => [
                'collection_key' => 'news',
                'order_direction' => 'asc',
                'listing_projection' => [
                    'order' => [
                        'field' => 'entry.published_at',
                        'direction' => 'desc',
                    ],
                ],
            ],
        ], 'es', $this->context(
            [['collection_key' => 'news', 'slug' => 'noticias']],
            ['data' => [], 'meta' => []],
            '/',
            null,
            $capturedQuery,
        ));

        $vm->vars();

        $this->assertIsArray($capturedQuery);
        $this->assertSame('field:entry.published_at', $capturedQuery['order_by']);
        $this->assertSame('desc', $capturedQuery['order_direction']);
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
        $this->assertSame('', $vars['canonicalViewAllUrl']);
        $this->assertSame('1/1', $vars['imageAspectRatio']);
    }
}
