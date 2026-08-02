<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\EventItemGalleryViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EventItemGalleryViewModelTest extends CIUnitTestCase
{
    public function testDoesNotFallBackToConfiguredPlaceholderImagesForARealEventWithNoGallery(): void
    {
        // A real event bound to the block, but with no gallery of its own — even though the
        // block's own config has admin-authored placeholder images (meant only for the
        // block-editor preview), they must never be substituted in for a real event.
        $vm = new EventItemGalleryViewModel(
            ['block_config' => ['fallback_gallery_images' => ['https://images.unsplash.com/photo-placeholder']]],
            'es',
            ['event_item' => ['id' => 1, 'title' => 'Función Real', 'gallery_images' => []]]
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['hasEvent']);
        $this->assertSame([], $vars['gallery']);
    }

    public function testUsesTheRealGalleryWhenTheEventHasOne(): void
    {
        $vm = new EventItemGalleryViewModel(
            ['block_config' => ['fallback_gallery_images' => ['https://images.unsplash.com/photo-placeholder']]],
            'es',
            ['event_item' => ['id' => 1, 'title' => 'Función Real', 'gallery_images' => [['url' => 'https://hub.local/real-photo.jpg']]]]
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['hasEvent']);
        $this->assertSame([['url' => 'https://hub.local/real-photo.jpg']], $vars['gallery']);
    }

    public function testWithoutABoundEventReportsPreviewStateAndAnEmptyGallery(): void
    {
        $vm = new EventItemGalleryViewModel(
            ['block_config' => ['fallback_gallery_images' => ['https://images.unsplash.com/photo-placeholder']]],
            'es',
            []
        );

        $vars = $vm->vars();

        $this->assertFalse($vars['hasEvent']);
        $this->assertSame([], $vars['gallery']);
    }
}
