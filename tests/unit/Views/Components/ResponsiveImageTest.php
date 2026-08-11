<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Components;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ResponsiveImageTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset the image count before each test run
        \Config\Services::blockRenderer()->render([]);
    }

    protected function tearDown(): void
    {
        \Config\Services::reset(true);
        parent::tearDown();
    }

    public function testRendersSrcsetForPicsumUrls(): void
    {
        $html = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1040/1200/900',
            'alt' => 'Sample alt text',
            'class' => 'test-class',
        ]);

        $this->assertStringContainsString('src="https://picsum.photos/id/1040/1200/900"', $html);
        $this->assertStringContainsString('alt="Sample alt text"', $html);
        $this->assertStringContainsString('class="test-class"', $html);
        $this->assertStringContainsString('srcset="https://picsum.photos/id/1040/480/360 480w, https://picsum.photos/id/1040/800/600 800w, https://picsum.photos/id/1040/1200/900 1200w"', $html);
        $this->assertStringContainsString('sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 1200px"', $html);
    }

    public function testDoesNotRenderSrcsetForNonPicsumUrls(): void
    {
        $html = view('components/responsive-image', [
            'src' => 'https://example.test/image.jpg',
            'alt' => 'Sample alt text',
        ]);

        $this->assertStringContainsString('src="https://example.test/image.jpg"', $html);
        $this->assertStringNotContainsString('srcset=', $html);
        $this->assertStringNotContainsString('sizes=', $html);
    }

    public function testCanDisableSrcsetForDynamicImages(): void
    {
        $html = view('components/responsive-image', [
            'src'        => 'https://picsum.photos/id/1040/1200/900',
            'alt'        => 'Dynamic image',
            'responsive' => false,
        ]);

        $this->assertStringContainsString('src="https://picsum.photos/id/1040/1200/900"', $html);
        $this->assertStringNotContainsString('srcset=', $html);
        $this->assertStringNotContainsString('sizes=', $html);
    }

    public function testImageSequenceOrderControlsEagerLoadingAndFetchpriority(): void
    {
        // First image gets loading="eager" and fetchpriority="high"
        $html1 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1040/1200/900',
            'alt' => 'Img 1',
        ]);
        $this->assertStringContainsString('loading="eager"', $html1);
        $this->assertStringContainsString('fetchpriority="high"', $html1);

        // Second image gets loading="eager" but no fetchpriority
        $html2 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1041/1200/900',
            'alt' => 'Img 2',
        ]);
        $this->assertStringContainsString('loading="eager"', $html2);
        $this->assertStringNotContainsString('fetchpriority=', $html2);

        // Third and fourth also loading="eager"
        $html3 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1042/1200/900',
            'alt' => 'Img 3',
        ]);
        $this->assertStringContainsString('loading="eager"', $html3);

        $html4 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1043/1200/900',
            'alt' => 'Img 4',
        ]);
        $this->assertStringContainsString('loading="eager"', $html4);

        // Fifth image gets loading="lazy"
        $html5 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1044/1200/900',
            'alt' => 'Img 5',
        ]);
        $this->assertStringContainsString('loading="lazy"', $html5);
    }

    public function testImageSequenceOrderOnMobile(): void
    {
        $userAgent = $this->createMock(\CodeIgniter\HTTP\UserAgent::class);
        $userAgent->method('isMobile')->willReturn(true);

        $request = $this->createMock(\CodeIgniter\HTTP\IncomingRequest::class);
        $request->method('getUserAgent')->willReturn($userAgent);

        \Config\Services::injectMock('request', $request);

        // Reset the image count before each test run
        \Config\Services::blockRenderer()->render([]);

        // First image gets loading="eager" and fetchpriority="high"
        $html1 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1040/1200/900',
            'alt' => 'Img 1',
        ]);
        $this->assertStringContainsString('loading="eager"', $html1);
        $this->assertStringContainsString('fetchpriority="high"', $html1);

        // Second image gets loading="lazy" (on mobile, maxEager is 1)
        $html2 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1041/1200/900',
            'alt' => 'Img 2',
        ]);
        $this->assertStringContainsString('loading="lazy"', $html2);
        $this->assertStringNotContainsString('fetchpriority=', $html2);
    }

    public function testRendersCustomAttributes(): void
    {
        $html = view('components/responsive-image', [
            'src' => 'https://example.test/image.jpg',
            'alt' => 'Custom Attribute Alt',
            'attributes' => 'data-custom-attribute="test"',
        ]);

        $this->assertStringContainsString('data-custom-attribute="test"', $html);
    }

    public function testRendersSrcsetFromVariants(): void
    {
        $variants = [
            'lg' => ['url' => 'https://example.test/image_lg.webp', 'width' => 1200, 'height' => 800],
            'sm' => ['url' => 'https://example.test/image_sm.webp', 'width' => 480, 'height' => 320],
        ];

        $html = view('components/responsive-image', [
            'src' => 'https://example.test/image.jpg',
            'alt' => 'Variants Alt',
            'variants' => $variants,
        ]);

        $this->assertStringContainsString('srcset="https://example.test/image_lg.webp 1200w, https://example.test/image_sm.webp 480w"', $html);
        $this->assertStringContainsString('sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 1200px"', $html);
        $this->assertStringContainsString('src="https://example.test/image.jpg"', $html);
    }

    public function testUsesThePreferredOptimizedVariantAsFallbackSource(): void
    {
        $html = view('components/responsive-image', [
            'src' => 'https://example.test/image-original.jpg',
            'alt' => 'Optimized variant',
            'preferredVariant' => 'sd',
            'variants' => [
                'sm' => ['url' => 'https://example.test/image_sm.webp', 'width' => 400, 'height' => 267],
                'md' => ['url' => 'https://example.test/image_md.webp', 'width' => 800, 'height' => 533],
            ],
        ]);

        // `sd` is supported for deployments that expose it; this checkout's
        // equivalent generated variant is `sm`, which must be selected safely.
        $this->assertStringContainsString('src="https://example.test/image_sm.webp"', $html);
        $this->assertStringNotContainsString('src="https://example.test/image-original.jpg"', $html);
        $this->assertStringContainsString('srcset="https://example.test/image_sm.webp 400w, https://example.test/image_md.webp 800w"', $html);
    }

    public function testBoundsResponsiveCandidatesForAThumbnailSlot(): void
    {
        $html = view('components/responsive-image', [
            'src' => 'https://example.test/image-original.jpg',
            'alt' => 'Bounded card image',
            'preferredVariant' => 'sd',
            'maxVariantWidth' => 640,
            'sizes' => '(max-width: 767px) 100vw, 33vw',
            'variants' => [
                'sm' => ['url' => 'https://example.test/image_sm.webp', 'width' => 400, 'height' => 533],
                'md' => ['url' => 'https://example.test/image_md.webp', 'width' => 750, 'height' => 1000],
            ],
        ]);

        $this->assertStringContainsString('src="https://example.test/image_sm.webp"', $html);
        $this->assertStringContainsString('srcset="https://example.test/image_sm.webp 400w"', $html);
        $this->assertStringNotContainsString('image_md.webp', $html);
        $this->assertStringContainsString('sizes="(max-width: 767px) 100vw, 33vw"', $html);
    }

    public function testRegistersPreloadInBlockRenderer(): void
    {
        $blockRenderer = \Config\Services::blockRenderer();
        $this->assertEmpty($blockRenderer->getPreloads());

        // First image gets loading="eager" and fetchpriority="high", which triggers preloading
        $html1 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1040/1200/900',
            'alt' => 'Img 1',
        ]);

        $preloads = $blockRenderer->getPreloads();
        $this->assertCount(1, $preloads);
        $this->assertSame('https://picsum.photos/id/1040/1200/900', $preloads[0]['src']);
        $this->assertStringContainsString('https://picsum.photos/id/1040/480/360 480w', $preloads[0]['srcset']);

        // Second image doesn't get fetchpriority="high", so it doesn't add a new preload
        $html2 = view('components/responsive-image', [
            'src' => 'https://picsum.photos/id/1041/1200/900',
            'alt' => 'Img 2',
        ]);

        $this->assertCount(1, $blockRenderer->getPreloads());
    }
}
