<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\HeroSliderViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HeroSliderViewModelTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        service('request')->setLocale('es');
    }

    public function testBuildsSlidesFromChildren(): void
    {
        $vm = new HeroSliderViewModel([
            'children' => [
                [
                    'block_config' => [
                        'image' => [
                            'source_kind' => 'external_url',
                            'file_id'     => null,
                            'url'         => 'https://cdn.test/a.jpg',
                        ],
                    ],
                    'block_data' => [
                        'heading'   => 'First',
                        'subtitle'  => 'Sub',
                        'cta_label' => 'Go',
                        'cta_url'   => '/contacto',
                    ],
                ],
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertCount(1, $vars['slides']);
        $this->assertSame('https://cdn.test/a.jpg', $vars['slides'][0]['image']['url']);
        $this->assertSame('First', $vars['slides'][0]['heading']);
        $this->assertStringContainsString('/es/contacto', $vars['slides'][0]['cta_url']);
        $this->assertStringContainsString('First', $vars['jsonSlides']);
    }

    public function testMissingImageFallsBackToSvgPlaceholder(): void
    {
        $vm = new HeroSliderViewModel([
            'children' => [
                ['block_data' => ['heading' => 'No image slide']],
            ],
        ], 'es');

        $slides = $vm->vars()['slides'];

        $this->assertStringStartsWith('data:image/svg+xml', $slides[0]['image']['url']);
        $this->assertStringContainsString(rawurlencode('No image slide'), $slides[0]['image']['url']);
    }

    public function testInvalidPositionsFallBackToDefaults(): void
    {
        $vm = new HeroSliderViewModel([
            'block_config' => [
                'caption_position'  => 'bogus',
                'controls_position' => 'bogus',
                'interval'          => 10,
                'overlay_opacity'   => 900,
            ],
            'children' => [
                ['block_data' => ['heading' => 'Slide']],
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('below', $vars['captionPosition']);
        $this->assertSame('below', $vars['controlsPosition']);
        $this->assertSame(1000, $vars['intervalMs'], 'Interval must be clamped to a 1s minimum');
        $this->assertSame(80, $vars['overlayPct'], 'Overlay opacity must be clamped to 80');
        $this->assertTrue($vars['captionIsBelow']);
        $this->assertFalse($vars['controlsIsOverlay']);
    }

    public function testTransitionIsConfigurableAndInvalidValuesUseFade(): void
    {
        $vm = new HeroSliderViewModel([
            'block_config' => ['transition' => 'slide'],
            'children' => [['block_data' => ['heading' => 'Slide']]],
        ], 'es');

        $this->assertSame('slide', $vm->vars()['transition']);

        $invalidVm = new HeroSliderViewModel([
            'block_config' => ['transition' => 'flip'],
            'children' => [['block_data' => ['heading' => 'Slide']]],
        ], 'es');

        $this->assertSame('fade', $invalidVm->vars()['transition']);
    }

    public function testEmptyChildrenYieldNoSlides(): void
    {
        $vm = new HeroSliderViewModel([], 'es');

        $this->assertSame([], $vm->vars()['slides']);
    }
}
