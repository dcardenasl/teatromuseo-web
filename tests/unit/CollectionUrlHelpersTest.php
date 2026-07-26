<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CollectionUrlHelpersTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config('App')->supportedLocales = ['aa', 'bb'];
        config('App')->defaultLocale = 'aa';
        service('request')->setValidLocales(['aa', 'bb']);
    }

    public function testCanonicalPathIsNormalized(): void
    {
        $locale = 'aa';
        $path = 'fixture-collection-' . $locale;
        service('request')->setLocale($locale);

        $collection = [
            'index_page' => [
                'localized_slugs' => [
                    $locale => '/' . $path,
                ],
            ],
        ];

        $this->assertSame('/' . $path, collection_url_path($collection));
    }

    public function testCanonicalPathMatchesAndReportsPrefix(): void
    {
        $locale = 'aa';
        $path = 'fixture-collection-' . $locale;
        $entrySlug = 'fixture-entry-' . $locale;
        service('request')->setLocale($locale);

        $collection = [
            'index_page' => [
                'localized_slugs' => [
                    $locale => $path,
                ],
            ],
        ];

        $info = collection_url_path_info($collection, $path . '/' . $entrySlug);

        $this->assertNotNull($info);
        $this->assertSame('/' . $path, $info['prefix']);
        $this->assertSame($entrySlug, $info['remainder']);
    }

    public function testCanonicalPathFallsBackToCollectionKeyWithoutIndexPage(): void
    {
        $locale = 'aa';
        $collectionKey = 'fixture-collection-key';
        $entrySlug = 'fixture-entry-' . $locale;
        service('request')->setLocale($locale);

        // Without a dedicated index page, entry links must still resolve to a
        // stable, collection-derived prefix — not depend on whichever page
        // happens to embed the listing block (see PageController::resolve()
        // Step 1, which relies on this prefix being routable).
        $collection = [
            'collection_key' => $collectionKey,
        ];

        $this->assertSame('/' . $collectionKey, collection_url_path($collection));

        $info = collection_url_path_info($collection, $collectionKey . '/' . $entrySlug);
        $this->assertNotNull($info);
        $this->assertSame('/' . $collectionKey, $info['prefix']);
        $this->assertSame($entrySlug, $info['remainder']);
    }

    public function testCanonicalPathReturnsEmptyWithoutSlugOrCollectionKey(): void
    {
        service('request')->setLocale('aa');

        $collection = [];

        $this->assertSame('', collection_url_path($collection));
        $this->assertNull(collection_url_path_info($collection, 'anything'));
    }

    public function testLocalizedCollectionUrlPathUsesTranslatedSlug(): void
    {
        $defaultLocale = 'aa';
        $secondaryLocale = 'bb';
        $defaultSlug = 'fixture-collection-' . $defaultLocale;
        $secondarySlug = 'fixture-collection-' . $secondaryLocale;
        service('request')->setLocale($defaultLocale);

        $collection = [
            'index_page' => [
                'localized_slugs' => [
                    $defaultLocale => $defaultSlug,
                    $secondaryLocale => $secondarySlug,
                ],
            ],
        ];

        $this->assertSame('/' . $defaultSlug, localized_collection_url_path($collection, $defaultLocale));
        $this->assertSame('/' . $secondarySlug, localized_collection_url_path($collection, $secondaryLocale));
    }

    public function testLocalizedEntryUrlsUseTranslatedCollectionAndEntrySlugs(): void
    {
        $defaultLocale = 'aa';
        $secondaryLocale = 'bb';
        $defaultCollectionSlug = 'fixture-collection-' . $defaultLocale;
        $secondaryCollectionSlug = 'fixture-collection-' . $secondaryLocale;
        $defaultEntrySlug = 'fixture-entry-' . $defaultLocale;
        $secondaryEntrySlug = 'fixture-entry-' . $secondaryLocale;
        service('request')->setLocale($defaultLocale);

        $collection = [
            'index_page' => [
                'localized_slugs' => [
                    $defaultLocale => $defaultCollectionSlug,
                    $secondaryLocale => $secondaryCollectionSlug,
                ],
            ],
        ];

        $entry = [
            'localized_slugs' => [
                $defaultLocale => $defaultEntrySlug,
                $secondaryLocale => $secondaryEntrySlug,
            ],
        ];

        $urls = localized_entry_urls($collection, $entry);
        $defaultPath = parse_url($urls[$defaultLocale], PHP_URL_PATH);
        $secondaryPath = parse_url($urls[$secondaryLocale], PHP_URL_PATH);

        $this->assertSame('/' . $defaultLocale . '/' . $defaultCollectionSlug . '/' . $defaultEntrySlug, $defaultPath);
        $this->assertSame('/' . $secondaryLocale . '/' . $secondaryCollectionSlug . '/' . $secondaryEntrySlug, $secondaryPath);
    }

    public function testCollectionDisplayTitleFallsBackToNameSlugThenKey(): void
    {
        $displayName = 'Fixture Collection Name';
        $slug = 'fixture-collection-name';
        $key = 'fixture-collection-key';

        $this->assertSame($displayName, collection_display_title([
            'listing_title' => '',
            'name' => $displayName,
            'slug' => $slug,
            'collection_key' => $key,
        ]));

        $this->assertSame('Fixture Collection Name', collection_display_title([
            'listing_title' => '',
            'name' => '',
            'slug' => $slug,
            'collection_key' => $key,
        ]));

        $this->assertSame('Fixture Collection Key', collection_display_title([
            'listing_title' => '',
            'name' => '',
            'slug' => '',
            'collection_key' => $key,
        ]));
    }

    public function testCollectionDisplayIntroFallsBackToDescription(): void
    {
        $description = 'Fixture primary description';
        $this->assertSame($description, collection_display_intro([
            'listing_intro' => '',
            'description' => $description,
        ]));

        $this->assertSame('', collection_display_intro([
            'listing_intro' => '',
            'description' => '',
        ]));
    }
}
