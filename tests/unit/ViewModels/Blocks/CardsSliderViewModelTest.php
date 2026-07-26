<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\CardsSliderViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CardsSliderViewModelTest extends CIUnitTestCase
{
    public function testOnlySlideCardChildrenBecomeCards(): void
    {
        $vm = new CardsSliderViewModel([
            'children' => [
                ['block_key' => 'slide_card', 'block_data' => ['title' => 'Card A', 'rating' => '4']],
                ['block_key' => 'metric_item', 'block_data' => ['title' => 'Not a card']],
                ['block_key' => 'slide_card', 'block_data' => ['title' => 'Card B']],
            ],
        ], 'es');

        $cards = $vm->vars()['cards'];

        $this->assertCount(2, $cards);
        $this->assertSame('Card A', $cards[0]['title']);
        $this->assertSame(4, $cards[0]['rating']);
        $this->assertSame(0, $cards[1]['rating']);
    }

    public function testSliderMathDerivedFromVisibleCount(): void
    {
        $vm = new CardsSliderViewModel([
            'block_config' => ['visible_count' => 2, 'layout' => 'slider'],
            'children'     => [
                ['block_key' => 'slide_card', 'block_data' => ['title' => 'A']],
                ['block_key' => 'slide_card', 'block_data' => ['title' => 'B']],
                ['block_key' => 'slide_card', 'block_data' => ['title' => 'C']],
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertTrue($vars['isSlider']);
        $this->assertSame(2, $vars['visibleCount']);
        $this->assertSame(50.0, (float) $vars['slideBasis']);
        $this->assertSame(2, $vars['dotCount']);
        $this->assertSame('max-w-6xl', $vars['sliderWidthClass']);
    }

    public function testGridLayoutAndDefaults(): void
    {
        $vm = new CardsSliderViewModel([
            'block_config' => ['layout' => 'grid', 'visible_count' => 99, 'interval' => 5],
            'block_data'   => ['section_title' => 'Testimonials'],
            'children'     => [
                ['block_key' => 'slide_card', 'block_data' => ['title' => 'A']],
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertFalse($vars['isSlider']);
        $this->assertSame(3, $vars['visibleCount'], 'visible_count must be clamped to 3');
        $this->assertSame(1000, $vars['interval'], 'interval must be clamped to 1s minimum');
        $this->assertSame('Testimonials', $vars['sectionTitle']);
    }

    public function testEmptyChildrenYieldNoCards(): void
    {
        $vm = new CardsSliderViewModel([], 'es');

        $this->assertSame([], $vm->vars()['cards']);
    }

    public function testCanonicalImageReferenceIsUsedForSlideCards(): void
    {
        $vm = new CardsSliderViewModel([
            'children' => [
                [
                    'block_key' => 'slide_card',
                    'block_config' => [
                        'image' => [
                            'source_kind' => 'external_url',
                            'file_id'     => null,
                            'url'         => 'https://cdn.test/card-a.jpg',
                        ],
                    ],
                    'block_data' => [
                        'title' => 'Card A',
                    ],
                ],
            ],
        ], 'es');

        $cards = $vm->vars()['cards'];

        $this->assertSame('https://cdn.test/card-a.jpg', $cards[0]['image']['url']);
    }
}
