<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use App\Libraries\BlockRenderer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Renders hero_slider through BlockRenderer (the production path), so the
 * HeroSliderViewModel wiring is exercised together with the template.
 *
 * @internal
 */
final class HeroSliderViewTest extends CIUnitTestCase
{
    public function testHeroSliderViewExposesLayoutPositions(): void
    {
        service('request')->setLocale('es');

        $html = (new BlockRenderer())->render([
            [
                'block_key'    => 'hero_slider',
                'block_config' => [
                    'caption_position'  => 'overlay_bottom',
                    'controls_position' => 'overlay_bottom',
                    'autoplay'          => false,
                ],
                'block_data'   => [],
                'children'     => [
                    [
                        'block_key'  => 'slide_banner',
                        'block_config' => [
                            'image' => [
                                'source_kind' => 'external_url',
                                'file_id'     => null,
                                'url'         => 'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%221200%22%20height%3D%22500%22%2F%3E',
                            ],
                        ],
                        'block_data' => [
                            'heading' => 'Hero title',
                            'subtitle' => 'Hero subtitle',
                            'cta_label' => 'Read more',
                            'cta_url' => '/contacto',
                        ],
                    ],
                    [
                        'block_key'  => 'slide_banner',
                        'block_config' => [
                            'image' => [
                                'source_kind' => 'external_url',
                                'file_id'     => null,
                                'url'         => 'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%221200%22%20height%3D%22500%22%2F%3E',
                            ],
                        ],
                        'block_data' => [
                            'heading' => 'Second slide',
                            'subtitle' => 'Second subtitle',
                            'cta_label' => 'Learn more',
                            'cta_url' => '/servicios',
                        ],
                    ],
                ],
            ],
        ], 'es');

        $this->assertStringContainsString('data-caption-position="overlay_bottom"', $html);
        $this->assertStringContainsString('data-controls-position="overlay_bottom"', $html);
        $this->assertStringContainsString('data-hero-caption-title', $html);
        $this->assertStringContainsString('Hero title', $html);
        $this->assertStringContainsString(
            'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%221200%22%20height%3D%22500%22%2F%3E',
            $html
        );
        $this->assertStringContainsString('href="', $html);
        $this->assertStringContainsString('/es/contacto', $html);
        $this->assertStringNotContainsString('scale-x-0', $html);
    }

    public function testHeroSliderRendersNothingWithoutSlides(): void
    {
        $html = (new BlockRenderer())->render([
            [
                'block_key'    => 'hero_slider',
                'block_config' => [],
                'block_data'   => [],
                'children'     => [],
            ],
        ], 'es');

        $this->assertSame('', trim($html));
    }
}
