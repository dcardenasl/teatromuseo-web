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

    public function testCanonicalizesLegacyHeroPathsPerLocale(): void
    {
        $this->assertSame('/cartelera', PublicPaths::canonicalPath('/cartelera', 'es'));
        $this->assertSame('/programming', PublicPaths::canonicalPath('/cartelera', 'en'));
        $this->assertSame('/programmation', PublicPaths::canonicalPath('/cartelera', 'fr'));
        $this->assertSame('/programacao', PublicPaths::canonicalPath('/cartelera', 'pt'));
        $this->assertSame('/theaterschool', PublicPaths::canonicalPath('/cursos', 'en'));
        $this->assertSame('/contact', PublicPaths::canonicalPath('/contacto', 'en'));
        $this->assertSame('/', PublicPaths::canonicalPath('/', 'fr'));
        $this->assertSame('/', PublicPaths::canonicalPath('/inicio', 'es'));
        $this->assertNull(PublicPaths::canonicalPath('/custom-destination', 'es'));
    }

    public function testNormalizesKnownLocalizedMenuPathsWithoutChangingCustomLinks(): void
    {
        $this->assertSame('/', PublicPaths::normalizeLocalizedPath('/es/inicio', 'es'));
        $this->assertSame('/', PublicPaths::normalizeLocalizedPath('/es', 'es'));
        $this->assertSame('/programming', PublicPaths::normalizeLocalizedPath('/en/cartelera', 'en'));
        $this->assertNull(PublicPaths::normalizeLocalizedPath('/es/custom-destination', 'es'));
        $this->assertNull(PublicPaths::normalizeLocalizedPath('https://example.com/inicio', 'es'));
        $this->assertNull(PublicPaths::normalizeLocalizedPath('/es/cartelera?page=2', 'es'));
    }
}
