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
                ['label' => 'Test Link', 'custom_url' => '/test', 'is_clickable' => true, 'children' => []],
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

    public function testNonClickableMenuItemsRenderWithoutAnchorsOrHashFallbacks(): void
    {
        $html = view('layouts/public', [
            'view' => 'page',
            'mainMenu' => ['items' => [
                [
                    'id' => 'about',
                    'label' => 'Nosotros',
                    'custom_url' => null,
                    'is_clickable' => false,
                    'children' => [
                        [
                            'id' => 'history',
                            'label' => 'Historia',
                            'custom_url' => null,
                            'is_clickable' => false,
                            'children' => [],
                        ],
                        [
                            'id' => 'contact',
                            'label' => 'Contacto',
                            'custom_url' => '/contacto',
                            'is_clickable' => true,
                            'children' => [],
                        ],
                    ],
                ],
                [
                    'id' => 'empty',
                    'label' => 'Sin enlace',
                    'custom_url' => null,
                    'is_clickable' => false,
                    'children' => [],
                ],
            ]],
            'footerMenu' => ['items' => []],
            'legalMenu' => ['items' => []],
            'settings' => [],
        ]);

        self::assertStringNotContainsString('href="#"', $html);
        self::assertSame(0, preg_match_all('/<a[^>]*>\s*Sin enlace\s*<\/a>/u', $html));
        self::assertSame(0, preg_match_all('/<a[^>]*>\s*Nosotros\s*<\/a>/u', $html));
        self::assertStringContainsString('href="' . site_url('/es/contacto') . '"', $html);
        self::assertStringContainsString('data-submenu-toggle', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
    }
}
