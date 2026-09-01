<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\CatalogItemHeaderViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CatalogItemHeaderViewModelTest extends CIUnitTestCase
{
    public function testDoesNotFallBackToConfiguredPlaceholderImageForARealItemWithNoCover(): void
    {
        // A real catalog item bound to the block, but with no cover of its own — even though
        // the block's own config has an admin-authored placeholder (meant only for the
        // block-editor preview), it must never be substituted in for a real item.
        $vm = new CatalogItemHeaderViewModel(
            ['block_config' => ['fallback_image_url' => 'https://images.unsplash.com/photo-placeholder']],
            'es',
            ['catalog_item' => ['id' => 1, 'name' => 'Obra Real', 'cover_image' => null]]
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['hasItem']);
        $this->assertSame('', $vars['imageUrl']);
    }

    public function testUsesTheRealCoverWhenTheItemHasOne(): void
    {
        $vm = new CatalogItemHeaderViewModel(
            ['block_config' => ['fallback_image_url' => 'https://images.unsplash.com/photo-placeholder']],
            'es',
            ['catalog_item' => ['id' => 1, 'name' => 'Obra Real', 'cover_image' => ['url' => 'https://hub.local/real-cover.jpg']]]
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['hasItem']);
        $this->assertSame('https://hub.local/real-cover.jpg', $vars['imageUrl']);
    }

    public function testWithoutABoundItemReportsPreviewState(): void
    {
        $vm = new CatalogItemHeaderViewModel(
            ['block_config' => ['fallback_image_url' => 'https://images.unsplash.com/photo-placeholder']],
            'es',
            []
        );

        $vars = $vm->vars();

        $this->assertFalse($vars['hasItem']);
    }
}
