<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\Listing\ListingVideoPresentation;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ListingVideoPresentationTest extends CIUnitTestCase
{
    public function testNormalizesAYouTubeVideoWithCanonicalPosterAndEmbedUrls(): void
    {
        $video = ListingVideoPresentation::normalize([
            'provider' => 'YouTube',
            'id' => 'dQw4w9WgXcQ',
            'url' => 'https://example.invalid/stale-video-url',
        ]);

        $this->assertSame([
            'provider' => 'youtube',
            'id' => 'dQw4w9WgXcQ',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
            'poster_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ], $video);
    }

    public function testRejectsIncompleteOrUnsupportedVideoPayloads(): void
    {
        $this->assertNull(ListingVideoPresentation::normalize([
            'provider' => 'youtube',
            'id' => 'too-short',
        ]));
        $this->assertNull(ListingVideoPresentation::normalize([
            'provider' => 'vimeo',
            'id' => '123',
            'url' => 'http://vimeo.com/123',
        ]));
        $this->assertNull(ListingVideoPresentation::normalize([
            'provider' => 'dailymotion',
            'id' => '123',
            'url' => 'https://example.com/video/123',
        ]));
    }

    public function testNormalizesVimeoOnlyWhenItsSecureUrlProducesAnEmbed(): void
    {
        $video = ListingVideoPresentation::normalize([
            'provider' => 'vimeo',
            'id' => '12345678',
            'url' => 'https://vimeo.com/12345678',
        ]);

        $this->assertIsArray($video);
        $this->assertSame('https://player.vimeo.com/video/12345678?autoplay=1', $video['embed_url']);
        $this->assertSame('', $video['poster_url']);
    }
}
