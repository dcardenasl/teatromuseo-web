# Block Requirements Contract

## Overview

The **Block Requirements Contract** defines how each block declares what external data it needs. This allows the `BlockAnalyzer` to detect requirements automatically and the `SmartPrefetch` to fetch all data in one parallel batch.

## The Contract

Every block that fetches data must implement or declare its requirements via:

```php
interface BlockRequirementProviderInterface
{
    /**
     * Detect what external data this block needs.
     *
     * Called during page render before BlockRenderer processes the block.
     * Should return the minimal set of IDs and fields needed.
     *
     * @param array $blockData  Block configuration from CMS (e.g., ['collection_ids' => '1,2,3'])
     * @return BlockRequirement|null
     */
    public function getRequirements(array $blockData): ?BlockRequirement;
}
```

## BlockRequirement Data Transfer Object

```php
class BlockRequirement
{
    public function __construct(
        public string $type,           // 'collections', 'events', 'categories', etc.
        public array $ids,             // [1, 2, 3] — IDs to fetch
        public array $fields = [],     // ['id', 'name', 'slug', ...] — which fields
        public ?int $cacheTtl = null,  // seconds; null = use service default
    ) {}
}
```

## Implementation Examples

### 1. Collection Grid Block

**Configuration from CMS:**
```json
{
  "key": "collection_grid",
  "data": {
    "collection_ids": "1,2,3",
    "columns": 3,
    "aspect_ratio": "1:1"
  }
}
```

**Requirement Provider:**
```php
class CollectionGridBlockRequirements implements BlockRequirementProviderInterface
{
    public function getRequirements(array $blockData): ?BlockRequirement
    {
        $idsString = $blockData['collection_ids'] ?? '';
        if (empty($idsString)) {
            return null; // No data to fetch
        }
        
        $ids = array_map('intval', explode(',', $idsString));
        
        return new BlockRequirement(
            type: 'collections',
            ids: $ids,
            fields: [
                'id', 'uuid', 'name', 'slug', 'cover_url', 'category_id',
                'summary', 'creator', 'origin', 'period',
            ],
        );
    }
}
```

### 2. Event Carousel Block

**Configuration from CMS:**
```json
{
  "key": "event_carousel",
  "data": {
    "event_ids": "10,20,30",
    "show_venues": true
  }
}
```

**Requirement Provider:**
```php
class EventCarouselBlockRequirements implements BlockRequirementProviderInterface
{
    public function getRequirements(array $blockData): ?BlockRequirement
    {
        $idsString = $blockData['event_ids'] ?? '';
        if (empty($idsString)) {
            return null;
        }
        
        $ids = array_map('intval', explode(',', $idsString));
        
        $fields = ['id', 'uuid', 'name', 'slug', 'start_date', 'cover_url'];
        
        if ($blockData['show_venues'] ?? false) {
            $fields[] = 'venue';
        }
        
        return new BlockRequirement(
            type: 'events',
            ids: $ids,
            fields: $fields,
        );
    }
}
```

### 3. Category Listing Block

**Configuration from CMS:**
```json
{
  "key": "category_listing",
  "data": {
    "fetch_all": true
  }
}
```

**Requirement Provider:**
```php
class CategoryListingBlockRequirements implements BlockRequirementProviderInterface
{
    private CategoryService $categoryService;
    
    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }
    
    public function getRequirements(array $blockData): ?BlockRequirement
    {
        if (!($blockData['fetch_all'] ?? false)) {
            return null;
        }
        
        // Some blocks need "all items" rather than specific IDs
        // In this case, request via ?fetch_all=1 instead of an ID list
        // SmartPrefetch handles this via a special "fetch_all" flag
        
        return new BlockRequirement(
            type: 'categories',
            ids: [-1], // Special sentinel: means "fetch all"
            fields: ['id', 'name', 'slug'],
        );
    }
}
```

### 4. Block with Multiple Requirements

**Configuration from CMS:**
```json
{
  "key": "exhibition_with_curator",
  "data": {
    "collection_item_id": 5,
    "curator_id": 100
  }
}
```

**Requirement Provider (returns array):**
```php
class ExhibitionWithCuratorBlockRequirements implements BlockRequirementProviderInterface
{
    /**
     * Some blocks need data from multiple sources.
     * Return an array of BlockRequirement objects.
     */
    public function getRequirements(array $blockData): array
    {
        $requirements = [];
        
        if (!empty($blockData['collection_item_id'])) {
            $requirements[] = new BlockRequirement(
                type: 'collections',
                ids: [(int) $blockData['collection_item_id']],
                fields: ['id', 'name', 'description', 'cover_url'],
            );
        }
        
        if (!empty($blockData['curator_id'])) {
            $requirements[] = new BlockRequirement(
                type: 'staff',
                ids: [(int) $blockData['curator_id']],
                fields: ['id', 'name', 'title', 'photo_url'],
            );
        }
        
        return $requirements;
    }
}
```

## Registration

Each block type registers its requirement provider in `Config\BlockRequirements`:

```php
// app/Config/BlockRequirements.php

namespace Config;

class BlockRequirements
{
    public const PROVIDERS = [
        'collection_grid'      => App\Services\Blocks\CollectionGridBlockRequirements::class,
        'event_carousel'       => App\Services\Blocks\EventCarouselBlockRequirements::class,
        'category_listing'     => App\Services\Blocks\CategoryListingBlockRequirements::class,
        'exhibition_curator'   => App\Services\Blocks\ExhibitionWithCuratorBlockRequirements::class,
        
        // Blocks that don't fetch external data (no provider)
        'text_block'           => null,
        'image_embed'          => null,
        'video_player'         => null,
        'cta_button'           => null,
    ];
}
```

## BlockAnalyzer Usage

The `BlockAnalyzerService` uses these providers to build the full requirement map:

```php
class BlockAnalyzerService
{
    public function analyze(array $blocks): array
    {
        $requirements = [];
        
        foreach ($blocks as $block) {
            $key = $block['key'] ?? null;
            if (!$key) {
                continue;
            }
            
            $providerClass = Config\BlockRequirements::PROVIDERS[$key] ?? null;
            if (!$providerClass) {
                continue; // No external data for this block
            }
            
            $provider = new $providerClass();
            $blockReqs = $provider->getRequirements($block['data'] ?? []);
            
            if ($blockReqs === null) {
                continue; // Block provided no requirements
            }
            
            $blockReqs = is_array($blockReqs) ? $blockReqs : [$blockReqs];
            
            foreach ($blockReqs as $req) {
                $this->mergeRequirement($requirements, $req);
            }
        }
        
        return $requirements;
    }
    
    private function mergeRequirement(array &$requirements, BlockRequirement $req): void
    {
        if (!isset($requirements[$req->type])) {
            $requirements[$req->type] = [
                'ids'   => [],
                'fields' => [],
            ];
        }
        
        $requirements[$req->type]['ids'] = array_unique(
            array_merge($requirements[$req->type]['ids'], $req->ids)
        );
        
        // Union of fields (take the maximum set)
        $requirements[$req->type]['fields'] = array_unique(
            array_merge($requirements[$req->type]['fields'], $req->fields)
        );
    }
}
```

## ViewModel Integration

Once data is prefetched and injected into `ContextHolder`, ViewModels retrieve it:

```php
class CollectionGridViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $blockData = $this->block['data'] ?? [];
        $idsString = $blockData['collection_ids'] ?? '';
        
        if (empty($idsString)) {
            return ['items' => []];
        }
        
        $ids = array_map('intval', explode(',', $idsString));
        
        // Fetch from prefetched context (no API call)
        $items = array_filter(
            array_map(
                fn ($id) => ContextHolder::get('collections', $id),
                $ids
            ),
            fn ($item) => $item !== null // Filter out missing items
        );
        
        return [
            'items' => $items,
            'columns' => $blockData['columns'] ?? 3,
            'aspectRatio' => $blockData['aspect_ratio'] ?? '1:1',
        ];
    }
}
```

## Field Selection Guidelines

Choose fields based on what the **block view actually renders**:

| Block Type | Typical Fields | Why |
|------------|---|---|
| Grid card | `id, name, slug, cover_url, summary` | Card needs minimal context |
| Detail page | `id, name, slug, description, content, cover_url, gallery_file_ids, created_at` | Detail shows everything |
| Thumbnail | `id, cover_url` | Only needs image |
| List (with links) | `id, name, slug` | Only needs link target |
| Timeline | `id, name, start_date, end_date` | Temporal data only |

**Rule:** Include a field only if the view actually renders it. Empty fields waste bandwidth and cache space.

## Caching Behavior

### Default Cache TTL

If `BlockRequirement::cacheTtl` is `null`, SmartPrefetch uses service defaults:

```php
$cacheTtl = $requirement->cacheTtl ?? SiteCollectionService::CACHE_TTL; // 3600s
```

### Custom Cache TTL per Block

For volatile content, override the TTL:

```php
return new BlockRequirement(
    type: 'events',
    ids: [1, 2, 3],
    fields: ['id', 'name', 'start_date'],
    cacheTtl: 60, // 1 minute — events change often
);
```

## Testing Requirements

### Unit Test: Requirement Provider

```php
public function testCollectionGridRequirementsExtractsIds()
{
    $provider = new CollectionGridBlockRequirements();
    $blockData = ['collection_ids' => '1,2,3'];
    
    $req = $provider->getRequirements($blockData);
    
    $this->assertEquals('collections', $req->type);
    $this->assertEquals([1, 2, 3], $req->ids);
    $this->assertContains('id', $req->fields);
    $this->assertContains('name', $req->fields);
}

public function testReturnsNullWhenNoIdsProvided()
{
    $provider = new CollectionGridBlockRequirements();
    $blockData = [];
    
    $req = $provider->getRequirements($blockData);
    
    $this->assertNull($req);
}
```

### Feature Test: BlockAnalyzer

```php
public function testAnalyzerMergesRequirementsFromMultipleBlocks()
{
    $blocks = [
        ['key' => 'collection_grid', 'data' => ['collection_ids' => '1,2']],
        ['key' => 'collection_grid', 'data' => ['collection_ids' => '2,3']],
    ];
    
    $reqs = $this->analyzer->analyze($blocks);
    
    // Both grids need collections, analyzer merges into one requirement
    $this->assertEquals([1, 2, 3], $reqs['collections']['ids']);
}
```

## Migration Checklist

When adding smart prefetch to an existing block:

- [ ] Create `BlockRequirements` provider class in `app/Services/Blocks/`.
- [ ] Implement `BlockRequirementProviderInterface::getRequirements()`.
- [ ] Register in `Config\BlockRequirements::PROVIDERS`.
- [ ] Update ViewModel to fetch from `ContextHolder` instead of service.
- [ ] Add unit tests for the requirement provider.
- [ ] Verify block still renders correctly.
- [ ] Test with missing/unavailable data (graceful fallback).

## Common Pitfalls

### ❌ Over-fetching fields

```php
// BAD: requesting fields the view doesn't use
$fields = ['id', 'name', 'description', 'content', 'image_url', 'created_at', 'updated_at'];

// GOOD: only what the view renders
$fields = ['id', 'name', 'image_url'];
```

### ❌ Hard-coded IDs instead of from block config

```php
// BAD: ignores what the CMS editor configured
public function getRequirements(array $blockData): ?BlockRequirement
{
    return new BlockRequirement(
        type: 'collections',
        ids: [1, 2, 3], // ← these are always fetched
    );
}

// GOOD: respects block data
public function getRequirements(array $blockData): ?BlockRequirement
{
    $ids = array_map('intval', explode(',', $blockData['collection_ids'] ?? ''));
    return empty($ids) ? null : new BlockRequirement(type: 'collections', ids: $ids);
}
```

### ❌ Fetching data inside ViewModel (defeats prefetch)

```php
// BAD: defeats the entire purpose of prefetch
public function vars(): array
{
    $item = $this->service->getById(123); // ← API call here, too late
    return ['item' => $item];
}

// GOOD: use ContextHolder
public function vars(): array
{
    $item = ContextHolder::get('collections', 123); // ← data already here
    return ['item' => $item];
}
```

## Related Documentation

- `SMART_PREFETCH.md` — how SmartPrefetch uses requirements.
- `SPARSE_FIELDSETS.md` — how field selection works.
- `BlockRenderer` — block rendering flow.
- `ContextHolder` — injected data storage.

## Changelog

### v1.0 (Planned)
- Initial `BlockRequirementProviderInterface`.
- Integration with `BlockAnalyzerService`.
- Sample providers for `collection_grid`, `event_carousel`, `category_listing`.
