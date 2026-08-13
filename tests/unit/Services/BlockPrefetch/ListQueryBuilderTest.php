<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BlockPrefetch;

use App\Services\BlockPrefetch\ListQueryBuilder;
use App\Services\BlockPrefetch\RequestQueryReader;
use App\Services\SiteCatalogService;
use App\Services\SiteEventService;
use PHPUnit\Framework\TestCase;

final class ListQueryBuilderTest extends TestCase
{
    private ListQueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ListQueryBuilder(new RequestQueryReader());
    }

    public function testEventItemsGridUsesGridFieldsAndAgendaSort(): void
    {
        $plan = $this->plan('collection_grid', ['collection_key' => 'cartelera']);

        $query = $this->builder->build($plan, 'event_items');

        $this->assertSame(SiteEventService::GRID_FIELDS, $query['fields']);
        $this->assertSame('agenda', $query['sort']);
        $this->assertSame(3, $query['per_page']);
    }

    public function testEventItemsListingUsesListFields(): void
    {
        $plan = $this->plan('collection_listing', ['collection_key' => 'cartelera']);

        $query = $this->builder->build($plan, 'event_items');

        $this->assertSame(SiteEventService::LIST_FIELDS, $query['fields']);
        $this->assertSame(12, $query['per_page']);
    }

    public function testCatalogItemsSortsByRequestedField(): void
    {
        $plan = $this->plan('collection_grid', ['collection_key' => 'museo', 'order_by' => 'entry.origin']);

        $query = $this->builder->build($plan, 'catalog_items');

        $this->assertSame(SiteCatalogService::GRID_FIELDS, $query['fields']);
        $this->assertSame('origin', $query['sort']);
    }

    public function testCatalogItemsAppliesResolvedCategoryId(): void
    {
        $plan = $this->plan('collection_grid', ['collection_key' => 'museo']);
        $plan['category_id'] = 7;

        $query = $this->builder->build($plan, 'catalog_items');

        $this->assertSame(7, $query['category_id']);
    }

    /**
     * Regression for docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md
     * §2.C — each CMS list block type requests only the listing_content
     * sub-keys its own consumer reads, or the ViewModel's narrower query
     * misses the prefetch cache and fetches live a second time.
     */
    public function testCmsCollectionRequestsOnlyTheSubKeysEachBlockTypeConsumes(): void
    {
        $cases = [
            'collection_grid' => 'listing_content.fields',
            'collection_listing' => 'listing_content.image,listing_content.secondary_action,listing_content.rich_text,listing_content.video,listing_content.publication_date,listing_content.date_fields,listing_content.fields',
            'collection_timeline' => 'listing_content.publication_date,listing_content.documents',
        ];

        foreach ($cases as $blockKey => $expectedInclude) {
            $plan = $this->plan($blockKey, ['collection_key' => 'news']);
            $query = $this->builder->build($plan, 'cms_collection');
            $this->assertSame($expectedInclude, $query['include'], "block_key={$blockKey}");
        }
    }

    public function testCmsOrderByPrefixesEntryAndBlockFieldsButNotPlainNames(): void
    {
        $plan = $this->plan('collection_grid', ['collection_key' => 'news', 'order_by' => 'entry.title']);
        $this->assertSame('field:entry.title', $this->builder->build($plan, 'cms_collection')['order_by']);

        $plan = $this->plan('collection_grid', ['collection_key' => 'news', 'order_by' => 'published_at']);
        $this->assertSame('published_at', $this->builder->build($plan, 'cms_collection')['order_by']);
    }

    public function testCatalogNeedsCategoryDependencyOnlyForListPlansWithACategoryFilter(): void
    {
        $listWithCategory = $this->plan('collection_grid', ['collection_key' => 'museo', 'category' => 'pintura']);
        $listWithCategory['kind'] = 'list';
        $this->assertTrue($this->builder->catalogNeedsCategoryDependency($listWithCategory));

        $listWithoutCategory = $this->plan('collection_grid', ['collection_key' => 'museo']);
        $listWithoutCategory['kind'] = 'list';
        $this->assertFalse($this->builder->catalogNeedsCategoryDependency($listWithoutCategory));

        $detail = $this->plan('event_item_header', ['collection_key' => 'museo', 'category' => 'pintura']);
        $detail['kind'] = 'detail';
        $this->assertFalse($this->builder->catalogNeedsCategoryDependency($detail));
    }

    public function testWantsFacetDefaultsBySourceTypeUnlessExplicitlySet(): void
    {
        $cmsPlan = $this->plan('collection_listing', ['collection_key' => 'news']);
        $cmsPlan['source_type'] = 'cms_collection';
        $this->assertTrue($this->builder->wantsFacet($cmsPlan, 'categories'));
        $this->assertFalse($this->builder->wantsFacet($cmsPlan, 'tags'));

        $eventPlan = $this->plan('collection_listing', ['collection_key' => 'cartelera']);
        $eventPlan['source_type'] = 'event_items';
        $this->assertTrue($this->builder->wantsFacet($eventPlan, 'tags'));

        $explicitOff = $this->plan('collection_listing', ['collection_key' => 'news', 'show_categories' => false]);
        $explicitOff['source_type'] = 'cms_collection';
        $this->assertFalse($this->builder->wantsFacet($explicitOff, 'categories'));
    }

    /** @param array<string, mixed> $payload */
    private function plan(string $blockKey, array $payload): array
    {
        return [
            'block_key' => $blockKey,
            'payload' => $payload,
            'source_type' => 'cms_collection',
            'kind' => 'list',
        ];
    }
}
