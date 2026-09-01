<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CollectionListingViewHierarchyTest extends CIUnitTestCase
{
    public function testCollectionListingDoesNotIntroduceASecondH1(): void
    {
        $html = view('blocks/collection_listing', [
            'isValid' => true,
            'collection' => [
                'id' => 1,
                'collection_key' => 'portafolio',
                'listing_title' => 'Nuestros Proyectos',
                'listing_intro' => '<p>Intro</p>',
                'default_meta_description' => 'Desc',
                'name' => 'Portafolio',
            ],
            'collectionKey' => 'portafolio',
            'collectionUrlPath' => 'portafolio',
            'localizedUrls' => [],
            'entries' => [],
            'pagination' => ['total_pages' => 1, 'per_page' => 12, 'current_page' => 1],
            'currentPage' => 1,
            'currentCategory' => '',
            'currentTag' => '',
            'currentQuery' => '',
            'orderBy' => 'published_at',
            'orderDirection' => 'desc',
            'layoutVariant' => 'portfolio',
            'imageAspectRatio' => '16/9',
            'cssClass' => '',
            'showSearch' => true,
            'showCategories' => true,
            'showTags' => true,
            'emptyMessage' => '',
            'introTitle' => 'Listado completo',
            'introText' => '<p>Texto</p>',
            'categories' => [],
            'tags' => [],
            'pageTitle' => 'Nuestros Proyectos',
            'metaDescription' => 'Desc',
            'lang' => 'es',
        ]);

        $this->assertSame(0, preg_match_all('/<h1\\b/i', $html));
        $this->assertSame(1, preg_match_all('/<h2\\b/i', $html));
    }
}
