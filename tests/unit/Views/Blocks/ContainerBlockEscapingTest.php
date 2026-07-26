<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ContainerBlockEscapingTest extends CIUnitTestCase
{
    public function testCssClassBreakoutIsNeutralized(): void
    {
        // An attacker trying to break out of the class attribute with a quote/angle-bracket injection
        $malicious = '"><script>alert(1)</script><div class="';

        $html = view('blocks/container', [
            'config'           => ['css_class' => $malicious],
            'renderedChildren' => '<p>Content</p>',
        ]);

        // Raw script tags must not appear in output
        $this->assertStringNotContainsString('<script>', $html);
        // Angle brackets are HTML-encoded, neutralizing the injection
        $this->assertStringContainsString('&lt;script&gt;', $html);
        // Children are still rendered
        $this->assertStringContainsString('<p>Content</p>', $html);
    }

    public function testDefaultCssClassRendersCorrectly(): void
    {
        $html = view('blocks/container', [
            'config'           => [],
            'renderedChildren' => '<p>Hello</p>',
        ]);

        // Default class renders with spaces intact (html context, not attr context)
        $this->assertStringContainsString('container mx-auto', $html);
    }
}
