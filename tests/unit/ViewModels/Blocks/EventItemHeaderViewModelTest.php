<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\EventItemHeaderViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EventItemHeaderViewModelTest extends CIUnitTestCase
{
    public function testDoesNotFallBackToConfiguredPlaceholderImageForARealEventWithNoCover(): void
    {
        // A real event bound to the block, but with no cover of its own — even though the
        // block's own config has an admin-authored placeholder (meant only for the
        // block-editor preview), it must never be substituted in for a real event.
        $vm = new EventItemHeaderViewModel(
            ['block_config' => ['fallback_image_url' => 'https://images.unsplash.com/photo-placeholder']],
            'es',
            ['event_item' => ['id' => 1, 'title' => 'Función Real', 'cover_image' => null]]
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['hasEvent']);
        $this->assertSame('', $vars['imageUrl']);
    }

    public function testUsesTheRealCoverWhenTheEventHasOne(): void
    {
        $vm = new EventItemHeaderViewModel(
            ['block_config' => ['fallback_image_url' => 'https://images.unsplash.com/photo-placeholder']],
            'es',
            ['event_item' => ['id' => 1, 'title' => 'Función Real', 'cover_image' => ['url' => 'https://hub.local/real-cover.jpg']]]
        );

        $vars = $vm->vars();

        $this->assertTrue($vars['hasEvent']);
        $this->assertSame('https://hub.local/real-cover.jpg', $vars['imageUrl']);
    }

    public function testWithoutABoundEventReportsPreviewState(): void
    {
        $vm = new EventItemHeaderViewModel(
            ['block_config' => ['fallback_image_url' => 'https://images.unsplash.com/photo-placeholder']],
            'es',
            []
        );

        $vars = $vm->vars();

        $this->assertFalse($vars['hasEvent']);
    }
}
