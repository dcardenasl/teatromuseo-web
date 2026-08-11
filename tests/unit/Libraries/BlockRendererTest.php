<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\BlockRenderer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class BlockRendererTest extends CIUnitTestCase
{
    private BlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new BlockRenderer();
    }

    public function testRendersFallsBackToUnknownBlockForUnknownType(): void
    {
        $html = $this->renderer->render([
            [
                'block_key'    => 'block_type_that_does_not_exist_xyz',
                'block_config' => [],
                'block_data'   => [],
                'children'     => [],
            ],
        ]);

        $this->assertNotEmpty($html);
    }

    public function testRendersKnownBlockType(): void
    {
        $html = $this->renderer->render([
            [
                'block_key'    => 'container',
                'block_config' => ['css_class' => 'my-container'],
                'block_data'   => [],
                'children'     => [],
            ],
        ]);

        $this->assertStringContainsString('my-container', $html);
    }

    public function testRendersGalleryItemWithCanonicalImageReference(): void
    {
        $html = $this->renderer->render([
            [
                'block_key'    => 'gallery_item',
                'block_config' => [
                    'image' => [
                        'source_kind' => 'external_url',
                        'file_id'     => null,
                        'url'         => 'https://picsum.photos/id/1040/1200/900',
                    ],
                ],
                'block_data'   => [
                    'alt'       => 'Gallery image',
                    'caption'   => 'Canonical caption',
                ],
                'children'     => [],
            ],
        ]);

        $this->assertStringContainsString('https://picsum.photos/id/1040/1200/900', $html);
        $this->assertStringContainsString('Gallery image', $html);
        $this->assertStringContainsString('Canonical caption', $html);
    }

    public function testRichTextRendersCanonicalContentField(): void
    {
        $html = $this->renderer->render([
            [
                'block_key'    => 'rich_text',
                'block_config' => [],
                'block_data'   => [
                    'content' => '<p>Festivales content</p>',
                ],
                'children'     => [],
            ],
        ]);

        $this->assertStringContainsString('Festivales content', $html);
    }

    public function testRendersNestedChildrenRecursively(): void
    {
        $html = $this->renderer->render([
            [
                'block_key'    => 'container',
                'block_config' => [],
                'block_data'   => [],
                'children'     => [
                    [
                        'block_key'    => 'container',
                        'block_config' => ['css_class' => 'child-block'],
                        'block_data'   => [],
                        'children'     => [],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('child-block', $html);
    }

    public function testEmptyBlockListReturnsEmptyString(): void
    {
        $this->assertSame('', $this->renderer->render([]));
    }

    public function testInjectsPrefetchedEventIntoDetailBlock(): void
    {
        $html = $this->renderer->render([
            [
                'block_key' => 'event_item_header',
                'block_config' => [],
                'block_data' => ['event_id' => 201],
                'children' => [],
            ],
        ], 'es', [
            'block_prefetch_complete' => true,
            'block_prefetch' => [
                '0' => [
                    'ok' => true,
                    'status' => 200,
                    'data' => [[
                        'id' => 201,
                        'title' => 'Prefetched event',
                        'event_type' => 'festival',
                        'occurrences' => [],
                    ]],
                    'meta' => [],
                ],
            ],
        ]);

        $this->assertStringContainsString('Prefetched event', $html);
    }

    public function testNormalizesHomepageNavigationToTheLocalizedPublicSlug(): void
    {
        $html = $this->renderer->render([
            [
                'block_key' => 'page_header',
                'block_config' => [],
                'block_data' => [
                    'heading' => 'Cartelera',
                    'breadcrumb_label' => 'Inicio',
                ],
                'navigation' => ['url' => '/es/inicio'],
                'children' => [],
            ],
        ], 'es');

        $this->assertStringContainsString('href="' . site_url('/es/inicio') . '"', $html);
    }

    public function testNormalizesNestedHomepageNavigationToTheLocalizedPublicSlug(): void
    {
        $html = $this->renderer->render([
            [
                'block_key' => 'hero_slider',
                'block_config' => [],
                'block_data' => [],
                'children' => [[
                    'block_key' => 'hero_slide',
                    'block_config' => ['navigation_mode' => 'internal'],
                    'block_data' => [
                        'heading' => 'Visítanos',
                        'image_alt_text' => 'Visítanos',
                    ],
                    'navigation' => ['url' => '/es/inicio'],
                    'children' => [],
                ]],
            ],
        ], 'es');

        $this->assertStringContainsString('href="' . site_url('/es/inicio') . '"', $html);
    }
}
