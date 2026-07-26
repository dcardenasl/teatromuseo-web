<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\VideoPlayerViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class VideoPlayerViewModelTest extends CIUnitTestCase
{
    public function testExtractsYouTubeIdFromCommonUrlVariants(): void
    {
        $this->assertSame('dQw4w9WgXcQ', VideoPlayerViewModel::getYouTubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', VideoPlayerViewModel::getYouTubeId('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', VideoPlayerViewModel::getYouTubeId('https://www.youtube.com/embed/dQw4w9WgXcQ'));
        $this->assertNull(VideoPlayerViewModel::getYouTubeId('https://vimeo.com/12345678'));
        $this->assertNull(VideoPlayerViewModel::getYouTubeId('https://cdn.test/video.mp4'));
    }

    public function testExtractsVimeoIdFromCommonUrlVariants(): void
    {
        $this->assertSame('12345678', VideoPlayerViewModel::getVimeoId('https://vimeo.com/12345678'));
        $this->assertSame('12345678', VideoPlayerViewModel::getVimeoId('https://vimeo.com/video/12345678'));
        $this->assertNull(VideoPlayerViewModel::getVimeoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function testEmbedUrlIncludesMuteAndLoopFlags(): void
    {
        $url = VideoPlayerViewModel::embedUrl('https://youtu.be/dQw4w9WgXcQ', true, true);

        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $url);
        $this->assertStringContainsString('mute=1', $url);
        $this->assertStringContainsString('loop=1&playlist=dQw4w9WgXcQ', $url);

        $vimeo = VideoPlayerViewModel::embedUrl('https://vimeo.com/12345678', true, false);
        $this->assertStringContainsString('player.vimeo.com/video/12345678', $vimeo);
        $this->assertStringContainsString('muted=1', $vimeo);
        $this->assertStringNotContainsString('loop=1', $vimeo);
    }

    public function testNativeVideoFilesProduceNoEmbedUrl(): void
    {
        $this->assertSame('', VideoPlayerViewModel::embedUrl('https://cdn.test/video.mp4', false, false));
    }

    public function testAspectRatioClassMapping(): void
    {
        $this->assertSame('aspect-video', VideoPlayerViewModel::aspectRatioClass('16/9'));
        $this->assertSame('aspect-[4/3]', VideoPlayerViewModel::aspectRatioClass('4/3'));
        $this->assertSame('aspect-auto', VideoPlayerViewModel::aspectRatioClass('auto'));
        $this->assertSame('aspect-video', VideoPlayerViewModel::aspectRatioClass('weird'));
    }

    public function testVarsForYouTubeBlock(): void
    {
        $vm = new VideoPlayerViewModel([
            'block_config' => ['mute' => true, 'aspect_ratio' => '4/3'],
            'block_data'   => ['video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'heading' => 'Demo'],
        ], 'es');

        $vars = $vm->vars();

        $this->assertTrue($vars['isIframe']);
        $this->assertSame('aspect-[4/3]', $vars['aspectRatioClass']);
        $this->assertSame('Demo', $vars['heading']);
        $this->assertStringStartsWith('video_', $vars['uniqueId']);
    }

    public function testVarsForEmptyBlockKeepEmptyVideoUrl(): void
    {
        $vm = new VideoPlayerViewModel([], 'es');

        $vars = $vm->vars();

        $this->assertSame('', $vars['videoUrl']);
        $this->assertFalse($vars['isIframe']);
    }

    public function testCanonicalConfigPosterReferenceIsUsed(): void
    {
        $vm = new VideoPlayerViewModel([
            'block_config' => [
                'poster' => [
                    'source_kind' => 'external_url',
                    'file_id'     => null,
                    'url'         => 'https://cdn.test/poster.jpg',
                ],
            ],
            'block_data' => [
                'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('https://cdn.test/poster.jpg', $vars['poster']['url']);
        $this->assertTrue($vars['isIframe']);
    }
}
