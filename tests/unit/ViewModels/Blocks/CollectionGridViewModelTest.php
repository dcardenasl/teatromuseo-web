<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;
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
    private function context(array $collections, array $entriesResult, string $path = '/'): array
    {
        $collectionService = $this->createMock(SiteCollectionService::class);
        $collectionService->method('getAll')->willReturn($collections);

        $entryService = $this->createMock(SiteEntryService::class);
        $entryService->method('list')->willReturn($entriesResult);

        $request = new IncomingRequest(config(App::class), new URI('http://localhost/' . ltrim($path, '/')), null, new UserAgent());
        $request->setLocale('es');

        return [
            'request' => $request,
            'siteCollectionService' => $collectionService,
            'siteEntryService' => $entryService,
        ];
    }

    public function testResolvesCanonicalUrlAndEntries(): void
    {
        $vm = new CollectionGridViewModel([
            'block_config' => ['collection_key' => 'news'],
            'block_data'   => ['section_title' => 'Noticias'],
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
                'title' => 'Post 1',
                'slug' => 'post-1',
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
            'block_data' => ['view_all_url' => '/fallback'],
        ], 'es', $this->context([], ['data' => [], 'meta' => []]));

        $vars = $vm->vars();

        $this->assertSame('cards', $vars['layoutVariant']);
        $this->assertSame('/fallback', $vars['canonicalViewAllUrl'], 'Manual URL used when collection is unknown');
        $this->assertStringContainsString('md:grid-cols-3', $vars['gridClass']);
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
    }
}
