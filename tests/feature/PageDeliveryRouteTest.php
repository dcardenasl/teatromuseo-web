<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

final class PageDeliveryRouteTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    private ?string $snapshotDirectory = null;

    /** @var list<string> */
    private array $originalManifestRoutes = [];

    /** @var list<string> */
    private array $originalBffRoutes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['aa', 'bb']);
        $this->originalManifestRoutes = config('App')->pageSnapshotManifestRoutes;
        $this->originalBffRoutes = config('App')->pageDeliveryBffRoutes;
        config('App')->pageSnapshotManifestRoutes = ['about'];
    }

    protected function tearDown(): void
    {
        if ($this->snapshotDirectory !== null) {
            $this->removeDirectory($this->snapshotDirectory);
        }

        config('App')->pageSnapshotDirectory = '';
        config('App')->pageSnapshotShared = false;
        config('App')->pageDeliveryAllowSynchronousFallback = false;
        config('App')->pageSnapshotManifestRoutes = $this->originalManifestRoutes;
        config('App')->pageDeliveryBffRoutes = $this->originalBffRoutes;

        parent::tearDown();
    }

    public function testConfiguredCmsRouteUsesTheSameSynchronousPageDeliveryContract(): void
    {
        config('App')->pageDeliveryEnabled = true;
        config('App')->pageDeliveryMode = 'sync';
        $this->domainAdapter->fakeGet('public-read/aa/pages/about', $this->page('about', 'Fixture about aa'));

        $result = $this->get('aa/about');

        $result->assertStatus(200);
        $result->assertSee('Fixture about aa');
        self::assertSame(1, $this->countPath('public-read/aa/pages/about'));
        // The redirect table is consulted for a configured route exactly like
        // it is for the legacy resolver — with no redirect fixture registered
        // it resolves to "no redirect" and the manifest page renders.
        self::assertContains('public/redirects/about', $this->domainAdapter->requestedPaths());
    }

    public function testSimpleCmsRouteUsesBffPageResolutionWhenOptedIn(): void
    {
        config('App')->pageDeliveryEnabled = true;
        config('App')->pageDeliveryMode = 'sync';
        config('App')->pageDeliveryBffRoutes = ['about'];
        $this->domainAdapter->fakeGet('public-read/aa/pages/about', $this->page('about', 'Fixture about via BFF'));

        $result = $this->get('aa/about');

        $result->assertStatus(200);
        $result->assertSee('Fixture about via BFF');
        self::assertSame(1, $this->countPath('public-read/aa/page-resolve/about'));
        self::assertNotContains('public-read/aa/pages/about', $this->domainAdapter->requestedPaths());
        self::assertNotContains('public-read/aa/page-bootstrap/about', $this->domainAdapter->requestedPaths());
        self::assertNotContains('public-read/aa/layout', $this->domainAdapter->requestedPaths());
    }

    public function testDynamicCmsRouteUsesBffBlockContextWithoutWebPrefetchCalls(): void
    {
        config('App')->pageDeliveryEnabled = true;
        config('App')->pageDeliveryMode = 'sync';
        config('App')->pageDeliveryBffRoutes = ['teatroescuela'];
        config('App')->pageSnapshotManifestRoutes = ['teatroescuela'];

        $page = $this->page('teatroescuela', 'Fixture TeatroEscuela via BFF');
        $page['page_type'] = 'cms_page';
        $page['blocks'] = [[
            'block_key' => 'collection_listing',
            'block_config' => [
                'source_type' => 'cms_collection',
                'collection_key' => 'teatroescuela',
                'items_limit' => 12,
            ],
            'block_data' => [],
            'children' => [],
        ]];

        $this->domainAdapter->fakeGet('public-read/aa/page-resolve/teatroescuela', [
            'outcome' => 'page',
            'redirect' => null,
            'page' => $page,
            'layout' => [
                'settings' => [],
                'mainMenu' => ['items' => []],
                'footerMenu' => ['items' => []],
                'legalMenu' => ['items' => []],
                'socialLinks' => [],
            ],
            'block_context' => [
                'block_prefetch' => [
                    '1' => [
                        'ok' => true,
                        'status' => 200,
                        'data' => [],
                        'meta' => [],
                        'stale' => false,
                    ],
                ],
                'block_prefetch_complete' => true,
                'form_definitions' => [],
                'cacheScopes' => ['collections', 'entries', 'collection_items'],
            ],
            'meta' => ['locale' => 'aa', 'route' => 'teatroescuela'],
            'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            'messages' => [],
        ]);

        $result = $this->get('aa/teatroescuela');

        $result->assertStatus(200);
        $result->assertSee('Fixture TeatroEscuela via BFF');
        self::assertSame(['public-read/aa/page-resolve/teatroescuela'], $this->domainAdapter->requestedPaths());
    }

    public function testRedirectOnAConfiguredRouteWinsOverItsOwnManifestContent(): void
    {
        config('App')->pageDeliveryEnabled = true;
        config('App')->pageDeliveryMode = 'sync';
        $this->domainAdapter->fakeGet('public-read/aa/pages/about', $this->page('about', 'Fixture about aa'));
        $this->domainAdapter->fakeGet('public/redirects/about', [
            'new_url' => '/elsewhere',
            'redirect_type' => 'permanent',
        ]);

        $result = $this->get('aa/about');

        $result->assertStatus(301);
        $result->assertHeader('Location', site_url('/aa/elsewhere'));
        $result->assertDontSee('Fixture about aa');
        // A redirect never touches page composition or the view renderer.
        self::assertSame(0, $this->countPath('public-read/aa/pages/about'));
    }

    public function testRedirectOnAConfiguredListingRouteWinsOverTheFallbackListing(): void
    {
        config('App')->pageDeliveryEnabled = true;
        config('App')->pageDeliveryMode = 'sync';
        config('App')->pageSnapshotManifestRoutes = ['events'];
        $this->domainAdapter->fakeGet('public/redirects/cartelera', [
            'new_url' => '/elsewhere',
            'redirect_type' => 'temporary',
        ]);

        $result = $this->get('aa/cartelera');

        $result->assertStatus(302);
        $result->assertHeader('Location', site_url('/aa/elsewhere'));
    }

    public function testSearchQueryOnAConfiguredRouteIsNeverPersistedAsASnapshot(): void
    {
        $this->snapshotDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatromuseo-page-delivery-route-' . bin2hex(random_bytes(6));
        $config = config('App');
        $config->pageDeliveryEnabled = true;
        $config->pageDeliveryMode = 'snapshot';
        $config->pageDeliveryAllowSynchronousFallback = true;
        $config->pageSnapshotDirectory = $this->snapshotDirectory;
        $config->pageSnapshotShared = true;
        $this->domainAdapter->fakeGet('public-read/aa/pages/about', $this->page('about', 'Fixture about aa'));

        $first = $this->get('aa/about?q=free-text-search-term');
        $first->assertStatus(200);
        $callsAfterFirst = count($this->domainAdapter->calls);

        $second = $this->get('aa/about?q=free-text-search-term');
        $second->assertStatus(200);

        // Same free-text query, requested twice: if it had been snapshotted,
        // the second request would reuse the file and add zero calls (as
        // testSnapshotHitAvoidsDomainCallsAfterTheFirstBuild proves for a
        // plain manifest route). It must always recompose synchronously.
        self::assertGreaterThan($callsAfterFirst, count($this->domainAdapter->calls));
    }

    public function testSnapshotHitAvoidsDomainCallsAfterTheFirstBuild(): void
    {
        $this->snapshotDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teatromuseo-page-delivery-route-' . bin2hex(random_bytes(6));
        $config = config('App');
        $config->pageDeliveryEnabled = true;
        $config->pageDeliveryMode = 'snapshot';
        $config->pageDeliveryAllowSynchronousFallback = true;
        $config->pageSnapshotDirectory = $this->snapshotDirectory;
        $config->pageSnapshotShared = true;
        $this->domainAdapter->fakeGet('public-read/aa/pages/about', $this->page('about', 'Fixture about aa'));

        $first = $this->get('aa/about');
        $first->assertStatus(200);
        $callsAfterFirst = count($this->domainAdapter->calls);

        $second = $this->get('aa/about');
        $second->assertStatus(200);

        self::assertGreaterThan(0, $callsAfterFirst);
        self::assertCount($callsAfterFirst, $this->domainAdapter->calls);
    }

    public function testConfiguredListingRouteUsesThePublicListingFallback(): void
    {
        config('App')->pageDeliveryEnabled = true;
        config('App')->pageDeliveryMode = 'sync';
        config('App')->pageSnapshotManifestRoutes = ['events'];

        $result = $this->get('aa/cartelera');

        $result->assertStatus(200);
        self::assertSame(1, $this->countPath('public-read/aa/pages/cartelera'));
    }

    private function page(string $slug, string $title): array
    {
        return [
            'page_type' => 'page',
            'title' => $title,
            'slug' => $slug,
            'excerpt' => 'Fixture page excerpt.',
            'meta_title' => $title,
            'meta_description' => 'Fixture page description.',
            'canonical_url' => '',
            'robots' => 'index, follow',
            'blocks' => [],
            'localized_slugs' => ['aa' => $slug, 'bb' => $slug],
        ];
    }

    private function countPath(string $path): int
    {
        return count(array_filter(
            $this->domainAdapter->requestedPaths(),
            static fn (string $requestedPath): bool => $requestedPath === $path,
        ));
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
