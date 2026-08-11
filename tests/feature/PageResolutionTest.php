<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

/**
 * Feature tests for PageController's dynamic resolver.
 *
 * @internal
 */
final class PageResolutionTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['aa', 'bb', 'cc']);
    }

    public function testResolvesCmsPage(): void
    {
        $locale = $this->locale();
        $slug = $this->slug('page');
        $title = $this->text('page-title');
        $this->fakeEmptyCollections($locale);
        $this->domainAdapter->fakeGet($this->domainPath('pages/' . $slug), $this->page($slug, $title));

        $result = $this->get($locale . '/' . $slug);

        $result->assertStatus(200);
        $result->assertSee($title);
    }

    public function testLegacyHomepageSlugsRedirectToLocalizedRoot(): void
    {
        $locale = $this->locale();

        foreach (['home', 'inicio'] as $slug) {
            $result = $this->get($locale . '/' . $slug);

            $result->assertStatus(301);
            $result->assertHeader('Location', lang_url('/', $locale));
        }
    }

    public function testHomepageResolvesLocalizedHomeSlugFromPublicReadListing(): void
    {
        $locale = $this->locale();
        $slug = 'accueil';
        $title = 'Fixture localized homepage ' . $locale;

        $this->domainAdapter->fakeGetFailure('public-read/' . $locale . '/pages/home');
        $this->domainAdapter->fakeGetFailure('public-read/' . $locale . '/pages/inicio');
        $this->domainAdapter->fakeGet('public-read/' . $locale . '/pages', [[
            'page_type' => 'home',
            'slug' => $slug,
        ]]);
        $this->domainAdapter->fakeGet('public-read/' . $locale . '/pages/' . $slug, $this->page(
            $slug,
            $title,
            ['page_type' => 'home'],
        ));

        $result = $this->get($locale . '/');

        $result->assertStatus(200);
        $result->assertSee($title);
    }

    public function testResolvesLocalizedPageInEachConfiguredLanguage(): void
    {
        foreach ($this->locales() as $position => $locale) {
            $slug = $this->slug('localized-page', $position);
            $title = $this->text('localized-title', $position);
            $this->fakeEmptyCollections($locale);
            $this->domainAdapter->fakeGet($this->domainPath('pages/' . $slug, $position), $this->page($slug, $title));

            $result = $this->get($locale . '/' . $slug);

            $result->assertStatus(200);
            $result->assertSee($title);
        }
    }

    public function testResolvesCollectionPrefixAsIndexBeforeCmsPage(): void
    {
        $locale = $this->locale();
        $collection = $this->collection('listing');
        $this->domainAdapter->fakeGet($this->domainPath('collections'), [$collection]);
        $this->domainAdapter->fakeGet($this->domainPath('entries/' . $collection['collection_key']), [], ['total_pages' => 1]);
        $this->domainAdapter->fakeGet($this->domainPath('categories/' . $collection['collection_key']), []);
        $this->domainAdapter->fakeGet($this->domainPath('pages/' . $collection['slug']), $this->page(
            $collection['slug'],
            $collection['name'],
            ['page_type' => 'collection_index', 'collection_id' => $collection['id']],
        ));

        $result = $this->get($locale . '/' . $collection['slug']);

        $result->assertStatus(200);
        $result->assertSee($collection['name']);
        $result->assertDontSee('CMS page that should not win');
    }

    public function testRedirectWinsOverCollectionPrefix(): void
    {
        $locale = $this->locale();
        $collection = $this->collection('listing');
        $this->domainAdapter->fakeGet($this->domainPath('collections'), [$collection]);
        $this->domainAdapter->fakeGet($this->domainPath('pages/obras'), $this->page(
            'obras',
            'CMS page that should not win',
            ['page_type' => 'collection_index', 'collection_id' => $collection['id']],
        ));
        $this->domainAdapter->fakeGet('public/redirects/obras', [
            'new_url' => '/cartelera',
            'redirect_type' => 'permanent',
        ]);

        $result = $this->get($locale . '/obras');

        $result->assertStatus(301);
        $result->assertHeader('Location', site_url('/' . $locale . '/cartelera'));
    }

    public function testResolvesCollectionEntry(): void
    {
        $locale = $this->locale();
        $collection = $this->collection('entries');
        $entrySlug = $this->slug('entry');
        $entryTitle = $this->text('entry-title');
        $this->domainAdapter->fakeGet($this->domainPath('collections'), [$collection]);
        $this->domainAdapter->fakeGet($this->domainPath('entries/' . $collection['collection_key'] . '/' . $entrySlug), $this->entry($entrySlug, $entryTitle));

        $result = $this->get($locale . '/' . $collection['slug'] . '/' . $entrySlug);

        $result->assertStatus(200);
        $result->assertSee($entryTitle);
        $result->assertSee($collection['name']);
    }

    public function testResolvesCollectionEntryWithEmptyListingTitleFallsBackToName(): void
    {
        $locale = $this->locale();
        $collection = $this->collection('fallback', listingTitle: '');
        $entrySlug = $this->slug('fallback-entry');
        $entryTitle = $this->text('fallback-entry-title');
        $this->domainAdapter->fakeGet($this->domainPath('collections'), [$collection]);
        $this->domainAdapter->fakeGet($this->domainPath('entries/' . $collection['collection_key'] . '/' . $entrySlug), $this->entry($entrySlug, $entryTitle));

        $result = $this->get($locale . '/' . $collection['slug'] . '/' . $entrySlug);

        $result->assertStatus(200);
        $result->assertSee($entryTitle);
        $result->assertSee($collection['name']);
    }

    public function testResolvesEntryFromCmsPageWithCollectionListingBlock(): void
    {
        $locale = $this->locale();
        $collection = $this->collection('block');
        $entrySlug = $this->slug('block-entry');
        $entryTitle = $this->text('block-entry-title');
        $this->domainAdapter->fakeGet($this->domainPath('collections'), [$collection]);
        $this->domainAdapter->fakeGet($this->domainPath('pages/' . $collection['slug']), $this->page(
            $collection['slug'],
            $collection['name'],
            ['blocks' => [[
                'block_key' => 'collection_listing',
                'block_config' => ['collection_id' => $collection['id']],
                'children' => [],
            ]]],
        ));
        $this->domainAdapter->fakeGet($this->domainPath('entries/' . $collection['collection_key'] . '/' . $entrySlug), $this->entry($entrySlug, $entryTitle));

        $result = $this->get($locale . '/' . $collection['slug'] . '/' . $entrySlug);

        $result->assertStatus(200);
        $result->assertSee($entryTitle);
        $result->assertSee($collection['name']);
    }

    public function testResolvesPermanentRedirect(): void
    {
        $locale = $this->locale();
        $oldSlug = $this->slug('old-page');
        $newSlug = $this->slug('new-page');
        $this->fakeEmptyCollections($locale);
        $this->domainAdapter->fakeGetFailure($this->domainPath('pages/' . $oldSlug));
        $this->domainAdapter->fakeGet('public/redirects/' . $oldSlug, [
            'new_url' => '/' . $locale . '/' . $newSlug,
            'redirect_type' => 'permanent',
        ]);

        $result = $this->get($locale . '/' . $oldSlug);

        $result->assertStatus(301);
        $result->assertHeader('Location', site_url('/' . $locale . '/' . $newSlug));
    }

    public function testReturns404WhenNothingMatches(): void
    {
        $locale = $this->locale();
        $slug = $this->slug('missing-page');
        $this->fakeEmptyCollections($locale);
        $this->domainAdapter->fakeGetFailure($this->domainPath('pages/' . $slug));
        $this->domainAdapter->fakeGetFailure('public/redirects/' . $slug);

        $result = $this->get($locale . '/' . $slug);

        $result->assertStatus(404);
        $result->assertSee($slug);
    }

    public function testUnknownPathDoesNotProbeEntriesForEveryCollection(): void
    {
        $locale = $this->locale();
        $missingPath = $this->slug('missing-route');
        $collections = [
            $this->collection('one'),
            $this->collection('two'),
            $this->collection('three'),
        ];

        $this->domainAdapter->fakeGet($this->domainPath('collections'), $collections);
        $this->domainAdapter->fakeGetFailure($this->domainPath('pages/' . $missingPath));
        $this->domainAdapter->fakeGetFailure('public/redirects/' . $missingPath);

        $result = $this->get($locale . '/' . $missingPath);

        $result->assertStatus(404);
        $entryRequests = array_values(array_filter(
            $this->domainAdapter->requestedPaths(),
            static fn (string $path): bool => str_starts_with($path, 'public/' . $locale . '/entries/'),
        ));

        $this->assertSame([], $entryRequests);
    }

    public function testCollectionEntryOnlyProbesTheMatchingCollection(): void
    {
        $locale = $this->locale();
        $target = $this->collection('target');
        $other = $this->collection('other');
        $entrySlug = $this->slug('indexed-entry');

        $this->domainAdapter->fakeGet($this->domainPath('collections'), [$target, $other]);
        $this->domainAdapter->fakeGet(
            $this->domainPath('entries/' . $target['collection_key'] . '/' . $entrySlug),
            $this->entry($entrySlug, $this->text('indexed-entry-title')),
        );

        $result = $this->get($locale . '/' . $target['slug'] . '/' . $entrySlug);

        $result->assertStatus(200);
        $this->assertContains(
            $this->domainPath('entries/' . $target['collection_key'] . '/' . $entrySlug),
            $this->domainAdapter->requestedPaths(),
        );
        $otherEntryRequests = array_values(array_filter(
            $this->domainAdapter->requestedPaths(),
            static fn (string $path): bool => str_contains($path, '/entries/' . $other['collection_key'] . '/'),
        ));

        $this->assertSame([], $otherEntryRequests);
    }

    /** @return array<string, mixed> */
    private function collection(string $role, ?string $listingTitle = null): array
    {
        $slug = $this->slug('collection-' . $role);
        $name = $this->text('collection-name-' . $role);

        return [
            'id' => 7000 + crc32($role) % 1000,
            'collection_key' => 'fixture-' . $role . '-key',
            'slug' => $slug,
            'name' => $name,
            'listing_title' => $listingTitle ?? $name,
            'listing_intro' => '',
            'default_meta_description' => $this->text('collection-meta-' . $role),
            'index_page' => ['localized_slugs' => $this->localizedSlugs('collection-' . $role)],
        ];
    }

    /** @return array<string, mixed> */
    private function page(string $slug, string $title, array $overrides = []): array
    {
        return array_replace([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $this->text('page-excerpt'),
            'meta_description' => $this->text('page-meta'),
            'canonical_url' => '',
            'blocks' => [],
            'localized_slugs' => $this->localizedSlugs($slug),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function entry(string $slug, string $title): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $this->text('entry-excerpt'),
            'meta_description' => $this->text('entry-meta'),
            'canonical_url' => '',
            'published_at' => '2026-01-01 00:00:00',
            'blocks' => [],
            'localized_slugs' => $this->localizedSlugs($slug),
        ];
    }

    private function fakeEmptyCollections(string $locale): void
    {
        $this->domainAdapter->fakeGet('public/' . $locale . '/collections', []);
    }

    private function domainPath(string $path, int $localePosition = 0): string
    {
        $prefix = (str_starts_with($path, 'entries') || str_starts_with($path, 'pages')) ? 'public-read/' : 'public/';
        return $prefix . $this->locale($localePosition) . '/' . $path;
    }

    private function slug(string $role, int $localePosition = 0): string
    {
        return 'fixture-' . $role . '-' . $this->locale($localePosition);
    }

    private function text(string $role, int $localePosition = 0): string
    {
        return 'Fixture ' . str_replace('-', ' ', $role) . ' ' . $this->locale($localePosition);
    }

    /** @return array<string, string> */
    private function localizedSlugs(string $role): array
    {
        $slugs = [];
        foreach (array_keys($this->locales()) as $position) {
            $slugs[$this->locale($position)] = $this->slug($role, $position);
        }

        return $slugs;
    }
}
