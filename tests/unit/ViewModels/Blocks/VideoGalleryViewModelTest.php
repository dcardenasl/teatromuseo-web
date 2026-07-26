<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\VideoGalleryViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class VideoGalleryViewModelTest extends CIUnitTestCase
{
    public function testUsesCanonicalPosterReferenceInsideRepeaterItems(): void
    {
        $vm = new VideoGalleryViewModel([
            'block_config' => ['columns' => '4'],
            'block_data' => [
                'title' => 'Videos',
                'videos' => [
                    [
                        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
                        'title' => 'Demo',
                        'poster' => [
                            'source_kind' => 'external_url',
                            'file_id'     => null,
                            'url'         => 'https://cdn.test/video-poster.jpg',
                        ],
                    ],
                ],
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('4', $vars['columns']);
        $this->assertCount(1, $vars['videos']);
        $this->assertSame('https://cdn.test/video-poster.jpg', $vars['videos'][0]['poster']['url']);
        $this->assertTrue($vars['videos'][0]['isIframe']);
    }
}
