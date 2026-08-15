<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\PublicSnapshotManifest;
use PHPUnit\Framework\TestCase;

final class PublicSnapshotManifestTest extends TestCase
{
    /** @var list<string> */
    private array $originalLocales;

    /** @var list<string> */
    private array $originalRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        $config = config('App');
        $this->originalLocales = $config->supportedLocales;
        $this->originalRoutes = $config->pageSnapshotManifestRoutes;
        $config->supportedLocales = ['es', 'en'];
        $config->pageSnapshotManifestRoutes = ['home', 'events', 'catalog', 'about'];
    }

    protected function tearDown(): void
    {
        $config = config('App');
        $config->supportedLocales = $this->originalLocales;
        $config->pageSnapshotManifestRoutes = $this->originalRoutes;

        parent::tearDown();
    }

    public function testStableRouteKeysResolveToLocaleSpecificPublicPaths(): void
    {
        $manifest = new PublicSnapshotManifest();

        $events = $manifest->requestFor('en', 'programming');
        $catalog = $manifest->requestFor('en', 'museum/collection');

        self::assertNotNull($events);
        self::assertSame('programming', $events->route);
        self::assertNotNull($catalog);
        self::assertSame('museum/collection', $catalog->route);
        self::assertNull($manifest->requestFor('en', 'events'));
    }

    public function testRequestsExpandsTheExplicitManifestWithoutCrawling(): void
    {
        $requests = (new PublicSnapshotManifest())->requests('en');

        self::assertSame(
            ['home', 'programming', 'museum/collection', 'about'],
            array_map(static fn ($request): string => $request->route, $requests),
        );
    }

    public function testUnlistedCmsRouteCannotEnterPageDelivery(): void
    {
        self::assertNull((new PublicSnapshotManifest())->requestFor('es', 'not-listed'));
    }

    public function testBffRouteUsesSnapshotEligibilityOnlyWhenAlsoInTheManifest(): void
    {
        $request = (new PublicSnapshotManifest())->requestForBff('es', 'about');

        self::assertNotNull($request);
        self::assertTrue($request->isSnapshotEligible());
        self::assertTrue($request->useBff);

        config('App')->pageSnapshotManifestRoutes = ['home'];
        $request = (new PublicSnapshotManifest())->requestForBff('es', 'about');

        self::assertNotNull($request);
        self::assertFalse($request->isSnapshotEligible());
    }

    public function testBffPolicyAcceptsUnlistedRoutesWithoutCreatingSnapshotCandidates(): void
    {
        $request = (new PublicSnapshotManifest())->requestForBff('en', 'noticias/entrada');

        self::assertNotNull($request);
        self::assertSame('noticias/entrada', $request->route);
        self::assertFalse($request->isSnapshotEligible());
    }
}
