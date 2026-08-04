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
        $this->assertSame('events', PublicPaths::routePath('events', 'en'));
        $this->assertSame('programme', PublicPaths::routePath('events', 'fr'));
        $this->assertSame('eventos', PublicPaths::routePath('events', 'pt'));
        $this->assertSame('museo/coleccion', PublicPaths::routePath('catalog', 'es'));
        $this->assertSame('museum/collection', PublicPaths::routePath('catalog', 'en'));
        $this->assertSame('musee/collection', PublicPaths::routePath('catalog', 'fr'));
        $this->assertSame('museu/colecao', PublicPaths::routePath('catalog', 'pt'));
        $this->assertNull(PublicPaths::routePath('unknown', 'es'));
    }
}
