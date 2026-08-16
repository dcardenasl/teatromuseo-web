<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PublicPaths;
use CodeIgniter\Test\CIUnitTestCase;

final class PublicPathsTest extends CIUnitTestCase
{
    public function testResolvesSemanticDomainRoutesPerLocale(): void
    {
        $this->assertSame('cartelera', PublicPaths::routePath('events', 'es'));
        $this->assertSame('programming', PublicPaths::routePath('events', 'en'));
        $this->assertSame('programmation', PublicPaths::routePath('events', 'fr'));
        $this->assertSame('programacao', PublicPaths::routePath('events', 'pt'));
        $this->assertSame('museo/coleccion', PublicPaths::routePath('catalog', 'es'));
        $this->assertSame('museum/collection', PublicPaths::routePath('catalog', 'en'));
        $this->assertSame('musee/collection', PublicPaths::routePath('catalog', 'fr'));
        $this->assertSame('museu/colecao', PublicPaths::routePath('catalog', 'pt'));
        $this->assertNull(PublicPaths::routePath('unknown', 'es'));
    }

    public function testExportsTheVersionedRouteContract(): void
    {
        $contract = PublicPaths::publicRouteContract();

        $this->assertSame(1, $contract['version']);
        $this->assertSame(['es', 'en', 'fr', 'pt'], $contract['locales']);
        $this->assertSame('programmation', $contract['routes']['events']['fr']);
        $this->assertContains('programmation', $contract['aliases']['events']);
        $this->assertSame('musee/collection', $contract['routes']['catalog']['fr']);
    }

    public function testResolvesLocalizedHomepageSegments(): void
    {
        $this->assertSame('inicio', PublicPaths::homepageSegment('es'));
        $this->assertSame('home', PublicPaths::homepageSegment('en'));
        $this->assertSame('accueil', PublicPaths::homepageSegment('fr'));
        $this->assertSame('inicio', PublicPaths::homepageSegment('pt'));
        $this->assertSame('/inicio', PublicPaths::homepagePath('es'));
    }

    public function testCanonicalizesLegacyHeroPathsPerLocale(): void
    {
        $this->assertSame('/cartelera', PublicPaths::canonicalPath('/cartelera', 'es'));
        $this->assertSame('/programming', PublicPaths::canonicalPath('/cartelera', 'en'));
        $this->assertSame('/programmation', PublicPaths::canonicalPath('/cartelera', 'fr'));
        $this->assertSame('/programacao', PublicPaths::canonicalPath('/cartelera', 'pt'));
        $this->assertSame('/theaterschool', PublicPaths::canonicalPath('/cursos', 'en'));
        $this->assertSame('/contact', PublicPaths::canonicalPath('/contacto', 'en'));
        $this->assertSame('/', PublicPaths::canonicalPath('/', 'fr'));
        $this->assertSame('/inicio', PublicPaths::canonicalPath('/inicio', 'es'));
        $this->assertSame('/inicio', PublicPaths::canonicalPath('/home', 'es'));
        $this->assertSame('/home', PublicPaths::canonicalPath('/home', 'en'));
        $this->assertSame('/accueil', PublicPaths::canonicalPath('/home', 'fr'));
        $this->assertNull(PublicPaths::canonicalPath('/custom-destination', 'es'));
    }

    public function testNormalizesKnownLocalizedMenuPathsWithoutChangingCustomLinks(): void
    {
        $this->assertSame('/inicio', PublicPaths::normalizeLocalizedPath('/es/inicio', 'es'));
        $this->assertSame('/inicio', PublicPaths::normalizeLocalizedPath('/es', 'es'));
        $this->assertSame('/home', PublicPaths::normalizeLocalizedPath('/en/home', 'en'));
        $this->assertSame('/programming', PublicPaths::normalizeLocalizedPath('/en/cartelera', 'en'));
        $this->assertNull(PublicPaths::normalizeLocalizedPath('/es/custom-destination', 'es'));
        $this->assertNull(PublicPaths::normalizeLocalizedPath('https://example.com/inicio', 'es'));
        $this->assertNull(PublicPaths::normalizeLocalizedPath('/es/cartelera?page=2', 'es'));
    }

    public function testResolvesRedirectTargetStatusByType(): void
    {
        $permanent = PublicPaths::resolveRedirectTarget(['new_url' => '/cartelera', 'redirect_type' => 'permanent'], 'es');
        $temporary = PublicPaths::resolveRedirectTarget(['new_url' => '/cartelera', 'redirect_type' => 'temporary'], 'es');
        $missingType = PublicPaths::resolveRedirectTarget(['new_url' => '/cartelera'], 'es');

        $this->assertSame(301, $permanent['status']);
        $this->assertSame(302, $temporary['status']);
        $this->assertSame(301, $missingType['status']);
    }

    public function testResolvesRedirectTargetNormalizesKnownInternalRoutesPerLocale(): void
    {
        $target = PublicPaths::resolveRedirectTarget(['new_url' => '/cartelera', 'redirect_type' => 'permanent'], 'pt');

        $this->assertSame('/programacao', $target['path']);
    }

    public function testResolvesRedirectTargetLeavesUnknownInternalPathsUntouched(): void
    {
        $target = PublicPaths::resolveRedirectTarget(['new_url' => '/custom-destination', 'redirect_type' => 'permanent'], 'es');

        $this->assertSame('/custom-destination', $target['path']);
    }

    public function testResolvesRedirectTargetLeavesExternalUrlsUntouched(): void
    {
        $target = PublicPaths::resolveRedirectTarget([
            'new_url' => 'https://example.com/cartelera',
            'redirect_type' => 'permanent',
        ], 'en');

        $this->assertSame('https://example.com/cartelera', $target['path']);
    }
}
