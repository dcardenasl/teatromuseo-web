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
                            'navigation_mode' => 'internal',
                            'navigation_target_type' => 'page',
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
                        ],
                        'navigation' => ['status' => 'resolved', 'route_key' => null, 'url' => '/es/contacto'],
                    ],
                    [
                        'block_key'  => 'slide_banner',
                        'block_config' => [
                            'navigation_mode' => 'none',
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

    public function testHeroSliderAcceptsLegacyFlatMediaFields(): void
    {
        $html = (new BlockRenderer())->render([
            [
                'block_key'    => 'hero_slider',
                'block_config' => [],
                'block_data'   => [],
                'children'     => [
                    [
                        'block_key'    => 'slide_banner',
                        'block_config' => [
                            'image_file_id' => 142,
                            'image_url'     => 'https://cdn.example.com/hero.webp',
                        ],
                        'block_data' => ['heading' => 'Legacy hero'],
                    ],
                ],
            ],
        ], 'es');

        $this->assertStringContainsString('https://cdn.example.com/hero.webp', $html);
        $this->assertStringNotContainsString('data:image/svg+xml', $html);
    }

    public function testHeroSliderUsesResponsivePublicImageVariants(): void
    {
        $html = (new BlockRenderer())->render([
            [
                'block_key'    => 'hero_slider',
                'block_config' => [],
                'block_data'   => [],
                'children'     => [[
                    'block_key'    => 'slide_banner',
                    'block_config' => [
                        'image' => [
                            'source_kind' => 'hub_file',
                            'file_id'     => 142,
                            'url'         => 'https://cdn.example.com/hero-original.jpg',
                            'variants'    => [
                                'sd' => ['url' => 'https://cdn.example.com/hero_sd.webp', 'width' => 640],
                                'lg' => ['url' => 'https://cdn.example.com/hero_lg.webp', 'width' => 1200],
                            ],
                        ],
                    ],
                    'block_data' => ['heading' => 'Responsive hero'],
                ]],
            ],
        ], 'es');

        $this->assertStringContainsString('src="https://cdn.example.com/hero_lg.webp"', $html);
        $this->assertStringContainsString('https://cdn.example.com/hero_sd.webp 640w', $html);
        $this->assertStringContainsString('https://cdn.example.com/hero_lg.webp 1200w', $html);
        $this->assertStringContainsString('sizes="100vw"', $html);
        $this->assertStringNotContainsString('src="https://cdn.example.com/hero-original.jpg"', $html);
    }

    public function testHeroSliderDoesNotPublishPrivateFileRouteAsImage(): void
    {
        $html = (new BlockRenderer())->render([
            [
                'block_key'    => 'hero_slider',
                'block_config' => [],
                'block_data'   => [],
                'children'     => [[
                    'block_key'    => 'slide_banner',
                    'block_config' => [
                        'image' => [
                            'source_kind' => 'file',
                            'file_id'     => 142,
                            'url'         => '/files/142/view',
                        ],
                    ],
                    'block_data' => ['heading' => 'Private route'],
                ]],
            ],
        ], 'es');

        $this->assertStringNotContainsString('/files/142/view', $html);
        $this->assertStringContainsString('data:image/svg+xml', $html);
    }
}
