<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Layouts;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PublicLayoutTest extends CIUnitTestCase
{
    public function testPublicLayoutRendersNestedSiteLogoInHeader(): void
    {
        $html = view('layouts/public', [
            'view' => 'page',
            'mainMenu' => ['items' => []],
            'footerMenu' => ['name' => 'Menu variant', 'items' => [
                ['label' => 'Test Link', 'custom_url' => '/test', 'children' => []],
            ]],
            'legalMenu' => ['items' => []],
            'settings' => [
                'site_name' => 'Mi Sitio',
                'site_logo' => [
                    'url' => 'http://localhost:8180/uploads/2026/06/28/logo_md.gif',
                ],
            ],
        ]);

        $this->assertStringContainsString(
            'src="http://localhost:8180/uploads/2026/06/28/logo_md.gif"',
            $html
        );
        $this->assertStringContainsString('alt="Mi Sitio"', $html);
        $this->assertStringContainsString('<span class="text-xl font-bold text-primary">Mi Sitio</span>', $html);
        $this->assertStringContainsString('>Menu variant</p>', $html);
        $this->assertStringNotContainsString('<style', $html);
    }
}
