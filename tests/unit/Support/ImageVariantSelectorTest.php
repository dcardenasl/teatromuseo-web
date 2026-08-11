<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ImageVariantSelector;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ImageVariantSelectorTest extends CIUnitTestCase
{
    public function testNormalizesJsonVariantsAndDropsUnresolvedFileRoutes(): void
    {
        $result = ImageVariantSelector::resolve(
            'https://example.test/original.jpg',
            json_encode([
                'blocked' => ['url' => '/files/1775/view', 'width' => 200],
                'sm' => ['url' => 'https://example.test/sm.webp', 'width' => 400],
                'md' => ['url' => 'https://example.test/md.webp', 'width' => 800],
            ], JSON_THROW_ON_ERROR),
            'sd',
        );

        $this->assertSame('https://example.test/sm.webp', $result['src']);
        $this->assertSame(
            'https://example.test/sm.webp 400w, https://example.test/md.webp 800w',
            $result['srcset'],
        );
        $this->assertStringNotContainsString('/files/1775/view', $result['srcset']);
    }

    public function testKeepsTheSmallestAvailableCandidateWhenAllExceedTheSlotWidth(): void
    {
        $result = ImageVariantSelector::resolve(
            'https://example.test/original.jpg',
            [
                'md' => ['url' => 'https://example.test/md.webp', 'width' => 800],
                'lg' => ['url' => 'https://example.test/lg.webp', 'width' => 1200],
            ],
            'sd',
            640,
        );

        $this->assertSame('https://example.test/md.webp', $result['src']);
        $this->assertSame('https://example.test/md.webp 800w', $result['srcset']);
    }
}
