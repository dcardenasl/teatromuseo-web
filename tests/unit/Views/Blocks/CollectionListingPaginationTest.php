<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CollectionListingPaginationTest extends CIUnitTestCase
{
    /**
     * @param array<string, mixed> $pagination
     */
    private function render(array $pagination, int $currentPage, string $currentTag = ''): string
    {
        return view('blocks/collection_listing', [
            'isValid' => true,
            'collection' => [
                'id' => 1,
                'collection_key' => 'companias',
                'listing_title' => 'Compañías',
                'listing_intro' => '',
                'default_meta_description' => '',
                'name' => 'Compañías',
            ],
            'collectionKey' => 'companias',
            'collectionUrlPath' => 'companias',
            'localizedUrls' => [],
            'entries' => [],
            'pagination' => $pagination,
            'currentPage' => $currentPage,
            'currentCategory' => '',
            'currentTag' => $currentTag,
            'currentQuery' => '',
            'orderBy' => 'name',
            'orderDirection' => 'asc',
            'layoutVariant' => 'cards',
            'imageAspectRatio' => '16/9',
            'cssClass' => '',
            'showSearch' => true,
            'showCategories' => true,
            'showTags' => true,
            'emptyMessage' => '',
            'introTitle' => '',
            'introText' => '',
            'categories' => [],
            'tags' => [],
            'pageTitle' => 'Compañías',
            'metaDescription' => '',
            'lang' => 'es',
        ]);
    }

    public function testNoPaginationNavWhenOnlyOnePage(): void
    {
        $html = $this->render(['total_pages' => 1, 'per_page' => 12, 'current_page' => 1], 1);

        $this->assertStringNotContainsString('data-listing-pagination', $html);
    }

    public function testMiddlePageShowsFirstLastAndNeighborsWithEllipsisGaps(): void
    {
        $html = $this->render(['total_pages' => 10, 'per_page' => 12, 'current_page' => 5], 5);

        // First and last page must always be reachable, plus a window around current (3-7).
        foreach ([1, 3, 4, 5, 6, 7, 10] as $page) {
            $this->assertMatchesRegularExpression('/>\s*' . $page . '\s*</', $html, "expected a link for page {$page}");
        }
        // Pages 2, 8, 9 fall outside the window and outside first/last — collapsed into ellipsis.
        $this->assertSame(2, substr_count($html, '&hellip;'));
        $this->assertStringContainsString('aria-current="page"', $html);
        // Both prev and next are available mid-range.
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
    }

    public function testFirstPageHasNoPreviousLink(): void
    {
        $html = $this->render(['total_pages' => 5, 'per_page' => 12, 'current_page' => 1], 1);

        $this->assertStringNotContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
    }

    public function testLastPageHasNoNextLink(): void
    {
        $html = $this->render(['total_pages' => 5, 'per_page' => 12, 'current_page' => 5], 5);

        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringNotContainsString('rel="next"', $html);
    }

    public function testPageLinksPreserveOtherQueryParameters(): void
    {
        $html = $this->render(['total_pages' => 3, 'per_page' => 12, 'current_page' => 1], 1, 'clown');

        $this->assertStringContainsString('tag=clown', $html);
    }

    public function testSmallPageCountRendersEveryPageWithNoEllipsis(): void
    {
        $html = $this->render(['total_pages' => 4, 'per_page' => 12, 'current_page' => 2], 2);

        foreach ([1, 2, 3, 4] as $page) {
            $this->assertMatchesRegularExpression('/>\s*' . $page . '\s*</', $html);
        }
        $this->assertStringNotContainsString('&hellip;', $html);
    }
}
