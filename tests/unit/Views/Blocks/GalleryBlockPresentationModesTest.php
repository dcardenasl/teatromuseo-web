<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class GalleryBlockPresentationModesTest extends CIUnitTestCase
{
    public function testInlinePreviewModeIsExposedToTheFrontend(): void
    {
        $html = view('blocks/gallery', [
            'config' => [
                'presentation_mode' => 'inline_preview',
                'columns'           => '3',
                'gap'               => 'medium',
                'css_class'         => '',
            ],
            'renderedChildren' => '<div data-gallery-item data-gallery-url="https://example.test/image.jpg" data-gallery-alt="Sample" data-gallery-caption="Caption"></div>',
        ]);

        $this->assertStringContainsString('data-gallery-mode="inline_preview"', $html);
        $this->assertStringContainsString('data-gallery-item', $html);
        $this->assertStringContainsString('Sample', $html);
    }

    public function testModalPreviewModeExposesAnAccessibleDialogShell(): void
    {
        $html = view('blocks/gallery', [
            'config' => [
                'presentation_mode' => 'modal_preview',
                'columns'           => '3',
                'gap'               => 'medium',
                'css_class'         => '',
            ],
            'renderedChildren' => '<div data-gallery-item data-gallery-url="https://example.test/image.jpg" data-gallery-alt="Sample" data-gallery-caption="Caption"></div>',
        ]);

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('const openImageLabel =', $html);
        $this->assertStringContainsString('const openImageCaptionLabel =', $html);
    }
}
