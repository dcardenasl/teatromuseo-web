<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Slug;
use PHPUnit\Framework\TestCase;

/** @internal */
final class SlugTest extends TestCase
{
    public function testSlugifyRemovesAccentsWithoutLeavingBrokenSeparators(): void
    {
        $this->assertSame('subete-al-escenario', Slug::slugify('Súbete al escenario'));
    }
}
