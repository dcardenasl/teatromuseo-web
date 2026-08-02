<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\CatalogItemGalleryViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CatalogItemGalleryViewModelTest extends CIUnitTestCase
{
    public function testDoesNotFallBackToConfiguredPlaceholderImagesForARealItemWithNoGallery(): void
    {
        // A real catalog item bound to the block, but with no gallery of its own — even though
        // the block's own config has admin-authored placeholder images (meant only for the
        // block-editor preview), they must never be substituted in for a real item.
        $vm = new CatalogItemGalleryViewModel(
            ['block_config' => ['fallback_gallery_images' => ['https://images.unsplash.com/photo-placeholder']]],
            'es',
            ['catalog_item' => ['id' => 1, 'title' => 'Obra Real', 'gallery_images' => []]]
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['hasItem']);
        $this->assertSame([], $vars['gallery']);
    }

    public function testUsesTheRealGalleryWhenTheItemHasOne(): void
    {
        $vm = new CatalogItemGalleryViewModel(
            ['block_config' => ['fallback_gallery_images' => ['https://images.unsplash.com/photo-placeholder']]],
            'es',
            ['catalog_item' => ['id' => 1, 'title' => 'Obra Real', 'gallery_images' => [['url' => 'https://hub.local/real-photo.jpg']]]]
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['hasItem']);
        $this->assertSame([['url' => 'https://hub.local/real-photo.jpg']], $vars['gallery']);
    }

    public function testWithoutABoundItemReportsPreviewStateAndAnEmptyGallery(): void
    {
        $vm = new CatalogItemGalleryViewModel(
            ['block_config' => ['fallback_gallery_images' => ['https://images.unsplash.com/photo-placeholder']]],
            'es',
            []
        );

        $vars = $vm->vars();

        $this->assertFalse($vars['hasItem']);
        $this->assertSame([], $vars['gallery']);
    }
}
