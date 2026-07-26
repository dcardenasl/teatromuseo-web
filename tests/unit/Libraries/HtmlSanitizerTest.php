<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\HtmlSanitizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HtmlSanitizerTest extends CIUnitTestCase
{
    public function testCleanPreservesSupportedRichTextMarkup(): void
    {
        $html = '<p>Texto <strong>enriquecido</strong> y <em>seguro</em></p>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function testCleanStripsUnsupportedMarkElementsWithoutThrowing(): void
    {
        $html = '<p><mark>Marcado</mark> y <strong>texto</strong></p>';

        $this->assertSame('<p>Marcado y <strong>texto</strong></p>', HtmlSanitizer::clean($html));
    }

    public function testCleanPreservesDivAndIdClassAttributes(): void
    {
        $html = '<div id="responsable" class="scroll-mt-20"><h2>1. Responsable</h2><p>Texto</p></div>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }
}
