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

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['aa', 'bb']);
        $this->originalManifestRoutes = config('App')->pageSnapshotManifestRoutes;
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
        self::assertNotContains('public/redirects/about', $this->domainAdapter->requestedPaths());
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
