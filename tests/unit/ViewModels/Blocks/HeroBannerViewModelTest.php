<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\HeroBannerViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HeroBannerViewModelTest extends CIUnitTestCase
{
    public function testUsesConfigImageWhenPresent(): void
    {
        $vm = new HeroBannerViewModel([
            'block_config' => [
                'image' => [
                    'source_kind' => 'external_url',
                    'file_id'     => null,
                    'url'         => 'https://cdn.test/hero-banner.jpg',
                ],
                'text_color' => '#123456',
            ],
            'block_data' => [
                'alt'        => 'Hero alt',
                'heading'    => 'About us',
                'subheading' => 'Who we are',
                'cta_label'  => 'Learn more',
                'cta_url'    => '/historia',
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('https://cdn.test/hero-banner.jpg', $vars['image']['url']);
        $this->assertSame('#123456', $vars['text_color']);
        $this->assertStringContainsString('/es/historia', $vars['cta_url']);
    }

    public function testMissingConfiguredImageUsesDarkText(): void
    {
        $vm = new HeroBannerViewModel([
            'block_data' => [],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('', $vars['image']['url']);
        $this->assertSame('#0f172a', $vars['text_color']);
    }
}
