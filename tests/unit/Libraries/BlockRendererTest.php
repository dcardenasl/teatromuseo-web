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
}
