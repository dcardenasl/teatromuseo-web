# Smart Prefetch Strategy

## Overview

The **Smart Prefetch** system solves the N+1 HTTP problem in the public website by:

1. **BlockAnalyzer** — scans all CMS page blocks **before** rendering.
2. **SmartPrefetch** — fetches all required data in **parallel** (via `WebApiClient::multiGet()`).
3. **ContextHolder** — injects prefetched data into block ViewModels.

This prevents a page with 5 blocks from making 5 sequential API calls (5× network latency) and instead makes 1 parallel batch call.

## The Problem: N+1 over HTTP

### Current (Sequential) Flow

```
PageController::resolve()
  ↓
  BlockRenderer::render(block 1)
    ↓ BlockService::fetchData() [HTTP GET #1]
    ↓
  BlockRenderer::render(block 2)
    ↓ BlockService::fetchData() [HTTP GET #2]  ← waits for #1 to finish
    ↓
  BlockRenderer::render(block 3)
    ↓ BlockService::fetchData() [HTTP GET #3]  ← waits for #2 to finish
    ↓
  ... (etc. for all blocks)
```

**Page load time:** 500ms latency × 5 blocks = **~2.5 seconds** before page is renderable.

### Optimized (Parallel) Flow

```
PageController::resolve()
  ↓
  BlockAnalyzer::analyze(all blocks)  ← collects all requirements
    ↓
  SmartPrefetch::prefetch(requirements)
    ↓
    [Parallel execution via curl_multi_init or WebApiClient::multiGet()]
    ├─ HTTP GET #1 (collection items)
    ├─ HTTP GET #2 (events)
    ├─ HTTP GET #3 (categories)
    └─ HTTP GET #4 (techniques)
    ↓
  ContextHolder::inject(results)
    ↓
  BlockRenderer renders all blocks (no API calls, all data in-process)
```

**Page load time:** 500ms latency × 1 parallel batch = **~500ms** for all blocks.

## Data Flow

### 1. BlockAnalyzer — Detect Requirements

The `BlockAnalyzerService` scans block configurations and returns a map of what each block needs:

```php
// Input: raw block data from CMS API
$blocks = [
    ['key' => 'collection_grid', 'data' => ['collection_ids' => '1,2,3']],
    ['key' => 'event_carousel', 'data' => ['event_ids' => '10,20']],
];

// Process
$requirements = $analyzer->analyze($blocks);

// Output: structured requirements
[
    'collections' => [
        'ids' => [1, 2, 3],
        'fields' => ['id', 'name', 'slug', 'cover_url', 'category_id'],
    ],
    'events' => [
        'ids' => [10, 20],
        'fields' => ['id', 'name', 'slug', 'start_date', 'cover_url', 'venue'],
    ],
]
```

### 2. SmartPrefetch — Parallel Execution

The `SmartPrefetchService` takes requirements and fetches all data in one batch:

```php
$results = $prefetcher->prefetch($requirements);

// Returns indexed by ID for fast lookup during render
[
    'collections' => [
        1 => ['id' => 1, 'name' => 'Payaso', ...],
        2 => ['id' => 2, 'name' => 'Museo', ...],
        3 => ['id' => 3, 'name' => 'Compañía', ...],
    ],
    'events' => [
        10 => ['id' => 10, 'name' => 'Show A', ...],
        20 => ['id' => 20, 'name' => 'Show B', ...],
    ],
]
```

### 3. ContextHolder — Make Data Available

The prefetched data is injected into a thread-local context so ViewModels can access it:

```php
ContextHolder::inject($prefetchedData);

// Later, inside a ViewModel or service:
$item = ContextHolder::get('collections', $id);
```

## Integration Points

### PageController

```php
public function resolve(string ...$segments): ResponseInterface
{
    // 1. Load CMS page and its blocks
    $page = $this->getPage(...);
    $blocks = $page['blocks'] ?? [];
    
    // 2. Analyze what data blocks need
    $analyzer = Services::blockAnalyzerService();
    $requirements = $analyzer->analyze($blocks);
    
    // 3. Fetch all data in parallel
    $prefetcher = Services::smartPrefetchService();
    $prefetched = $prefetcher->prefetch($requirements);
    
    // 4. Inject into context
    ContextHolder::inject($prefetched);
    
    // 5. Render (blocks now fetch from context, not API)
    return $this->renderCmsPage($page, ...);
}
```

### BlockRenderer

No changes needed — blocks work the same way, but data comes from `ContextHolder` instead of making API calls:

```php
// Inside a block's ViewModel
class CollectionGridViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $ids = $this->extractIdsFromBlockData();
        
        // Old way (N+1):
        // $items = $this->service->getByIds($ids);
        
        // New way (from prefetch):
        $items = array_map(
            fn ($id) => ContextHolder::get('collections', $id),
            $ids
        );
        
        return ['items' => $items];
    }
}
```

## Block Requirements Declaration

Each block declares what data it needs via a simple interface:

```php
interface BlockRequirementProviderInterface
{
    /**
     * Return the data dependencies for this block.
     * 
     * @param array $blockData  The block's data configuration from CMS
     * @return array{type: string, ids: list<int>, fields?: list<string>}
     */
    public function getRequirements(array $blockData): array;
}
```

### Example: Collection Grid Block

```php
class CollectionGridBlockRequirements implements BlockRequirementProviderInterface
{
    public function getRequirements(array $blockData): array
    {
        // Extract IDs from block config
        $ids = array_map('intval', explode(',', $blockData['collection_ids'] ?? ''));
        
        if (empty($ids)) {
            return [];
        }
        
        return [
            'type' => 'collections',
            'ids' => $ids,
            'fields' => [
                'id', 'name', 'slug', 'cover_url', 'category_id',
                'creator', 'origin', 'period', 'summary',
            ],
        ];
    }
}
```

## API Calls with Sparse Fieldsets

SmartPrefetch automatically uses sparse fieldsets to minimize payloads:

```php
// Generated API call:
GET /api/v1/public/es/collection-items
    ?ids=1,2,3
    &fields=id,name,slug,cover_url,category_id,creator,origin,period,summary
```

Payload: ~2 KB (vs. ~10 KB if fetching all fields).

## Error Handling & Fallbacks

### Missing Data

If an ID cannot be resolved (doesn't exist in the API), the prefetch marks it and the block handles the gap:

```php
$requirements = [
    'collections' => ['ids' => [1, 2, 3, 999], ...],
];

$results = $prefetcher->prefetch($requirements);
// Result: [1 => {...}, 2 => {...}, 3 => {...}]  // 999 omitted

// Inside ViewModel:
$items = array_filter(
    array_map(fn ($id) => ContextHolder::get('collections', $id), $ids),
    fn ($item) => $item !== null
);
```

### Upstream Failure

If the domain API is unreachable, SmartPrefetch falls back to stale cache (via `WebApiClient`'s built-in stale logic) or returns partial results:

```php
public function prefetch(array $requirements): array
{
    $results = [];
    
    foreach ($requirements as $type => $req) {
        try {
            $results[$type] = $this->fetchBatch($type, $req);
        } catch (Exception $e) {
            log_message('error', "SmartPrefetch failed for {$type}: {$e->getMessage()}");
            // Return empty or stale data, don't block page render
            $results[$type] = [];
        }
    }
    
    return $results;
}
```

Blocks that depend on unavailable data render gracefully (empty state, placeholder, etc.).

## Caching Strategy

### Three-Level Cache

1. **HTTP Response Cache** (PageController level)
   - Entire rendered page cached after first render.
   - TTL: 3600s (1 hour) for stable content.

2. **API Response Cache** (WebApiClient level)
   - Individual API calls cached separately.
   - TTL: 300s (5 minutes) by default, longer for stable content.

3. **Context-Injected Data** (In-process)
   - Prefetched data held in `ContextHolder` during page render.
   - No cache — fresh on every page load (but only one API call per unique requirement set).

### Cache Invalidation

When content changes, invalidation triggers a cascade:

```
Editor saves a collection item
  ↓
CMS Domain fires webhook to teatromuseo-web cache invalidator
  ↓
CacheInvalidator::invalidate(['collections', 'pages'])
  ↓
Deletes:
  - web_api_*_collections_*
  - web_api_*_pages_*
  - All http-response cache entries that depend on collections
  ↓
Next page load fetches fresh from API
```

## Testing

### Unit Test: BlockAnalyzer

```php
public function testAnalyzeDetectsCollectionGridRequirements()
{
    $blocks = [
        ['key' => 'collection_grid', 'data' => ['collection_ids' => '1,2,3']],
    ];
    
    $requirements = $this->analyzer->analyze($blocks);
    
    $this->assertArrayHasKey('collections', $requirements);
    $this->assertEquals([1, 2, 3], $requirements['collections']['ids']);
}
```

### Feature Test: SmartPrefetch

```php
public function testSmartPrefetchFetchesAllDataInOneRequest()
{
    $this->mockHttpClient([
        'GET /api/v1/public/es/collection-items?ids=1,2,3&fields=...'
            => ['ok' => true, 'data' => [...], 'meta' => [...]],
    ]);
    
    $requirements = ['collections' => ['ids' => [1, 2, 3], 'fields' => [...]]];
    $results = $this->prefetcher->prefetch($requirements);
    
    // Verify only ONE HTTP call was made
    $this->assertEquals(1, $this->httpClient->callCount());
}
```

### Integration Test: PageController

```php
public function testPageRendersWithPrefetchedData()
{
    $response = $this->get('/es/museo');
    
    $this->assertResponseCode(200);
    // Verify page contains expected content (no "Failed to load" messages)
    $this->assertStringContainsString('Collection Item Name', $response);
}
```

## Monitoring & Observability

### Logs to Watch

```
[smartprefetch] Prefetch for 3 requirements took 412ms (1 parallel batch)
[smartprefetch] Requirement type=collections ids=[1,2,3] → 3 results
[smartprefetch] Requirement type=events ids=[10,20] → 1 result, 1 missing
```

### Metrics to Track

- **Prefetch latency:** Time from `prefetch()` call to all data returned.
- **Hit ratio:** How many requested IDs were found vs. missing.
- **Cache effectiveness:** How many prefetch calls were skipped due to HTTP response cache.

## Performance Targets

After full SmartPrefetch implementation:

| Metric | Before | After | Target |
|--------|--------|-------|--------|
| Page load time (api latency) | 2.5s (5 blocks × 500ms) | 500ms | ✅ |
| API calls per page | 5–8 | 1–2 | ✅ |
| Payload size | 50–80 KB | 10–20 KB | ✅ |
| Server CPU (render) | high (concurrent I/O) | low (batched) | ✅ |

## Related Documentation

- `BLOCK_REQUIREMENTS.md` — how blocks declare their data needs.
- `SPARSE_FIELDSETS.md` — optimizing payloads with field selection.
- `WebApiClient::multiGet()` — parallel execution of API calls.
- `CacheInvalidator` — cache invalidation webhook receiver.

## Roadmap

- **Phase 1 (Task #5):** `BlockAnalyzerService` — detect requirements from blocks.
- **Phase 2 (Task #6):** `SmartPrefetchService` — parallel fetch with caching.
- **Phase 3 (Task #7):** Integrate into `PageController::resolve()`.
- **Phase 4 (Task #8):** `ParallelAliasResolver` — batch slug → ID resolution.
- **Phase 5 (Task #10):** Measure and verify performance gains in production/staging.
