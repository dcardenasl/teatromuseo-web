# Sparse Fieldsets

## Overview

Sparse fieldsets (JSON:API convention) allow API consumers to request only the fields they need, reducing payload size and network bandwidth. This document covers the `SparseFieldsetTrait` implementation in the Teatro Museo domain APIs and how to use it from the web layer.

## The Protocol

### Query Parameter Format

Add a `?fields=` query parameter to any API request:

```
GET /api/v1/public/es/collection-items?fields=id,name,slug,cover_url
GET /api/v1/public/es/events?fields=id,name,start_date,venue
```

Multiple field lists (one per resource type) are supported:

```
GET /api/v1/public/es/collection-items?fields[collection_items]=id,name,slug&fields[categories]=id,name
```

### Response Shape

The API returns **all fields in the response envelope** (e.g., `meta`, `status`, `ok`), but **only requested fields in each `data` item**:

```json
{
  "ok": true,
  "status": 200,
  "data": [
    {
      "id": 1,
      "name": "Payaso de Yeso",
      "slug": "payaso-de-yeso"
    }
  ],
  "meta": { "per_page": 20, "total": 365 }
}
```

If `?fields` is omitted, the API defaults to a **schema-declared listing** or **detail fieldset** depending on the endpoint (see "Default Fieldsets" below).

## Implementation in Domain APIs

### Catalog Domain — Collection Items (Reference)

**File:** `teatromuseo-catalog-domain/app/Controllers/Api/V1/Catalog/PublicCollectionItemController.php`

The controller defines two field whitelists and uses `SparseFieldsetTrait` to filter responses:

```php
use SparseFieldsetTrait;

private const LISTING_FIELDS = [
    'id', 'uuid', 'name', 'slug', 'slugs', 'category_id', 'cover_file_id',
    'cover_url', 'localized', 'summary', 'period', 'creator', 'origin',
];

private const DETAIL_FIELDS = [
    'id', 'uuid', 'name', 'slug', 'slugs', 'category_id', 'cover_file_id',
    'cover_url', 'gallery_file_ids', 'localized', 'summary', 'description',
    'content', 'period', 'creator', 'origin', 'techniques', 'materials',
    'translations', 'created_at', 'updated_at',
];

public function index(): ResponseInterface
{
    return $this->handleRequest(
        function (CollectionItemIndexRequestDTO $dto, SecurityContext $context): mixed {
            $fields = $this->parseFieldsParam(self::LISTING_FIELDS);
            $result = $this->collectionItemService->index($dto, $context)->toArray();
            
            foreach ($result['data'] as $key => $item) {
                $itemArray = $item instanceof DataTransferObjectInterface 
                    ? $item->toArray() 
                    : (array) $item;
                $result['data'][$key] = $this->sparseFilter($itemArray, $fields);
            }
            
            return $result;
        },
        CollectionItemIndexRequestDTO::class
    );
}
```

### Adding Sparse Fieldsets to a New Endpoint

1. **Define fieldsets as class constants:**

```php
private const LISTING_FIELDS = ['id', 'name', 'slug', ...];
private const DETAIL_FIELDS  = ['id', 'name', 'slug', 'description', ...];
```

2. **Parse the incoming `?fields` parameter:**

```php
$fields = $this->parseFieldsParam(self::LISTING_FIELDS);  // for index()
$fields = $this->parseFieldsParam(self::DETAIL_FIELDS);   // for show()
```

3. **Filter each data item:**

```php
foreach ($result['data'] as $key => $item) {
    $itemArray = $item instanceof DataTransferObjectInterface ? $item->toArray() : (array) $item;
    $result['data'][$key] = $this->sparseFilter($itemArray, $fields);
}
```

## Default Fieldsets

If the client does **not** send `?fields=`, the API uses these defaults:

| Endpoint | Default |
|----------|---------|
| `GET /api/v1/public/es/collection-items` | `LISTING_FIELDS` constant in the controller |
| `GET /api/v1/public/es/collection-items/{id}` | `DETAIL_FIELDS` constant in the controller |
| `GET /api/v1/public/es/events` | `LISTING_FIELDS` constant in the controller |
| `GET /api/v1/public/es/events/{id}` | `DETAIL_FIELDS` constant in the controller |

This means:
- **Backward compatible:** Clients that ignore sparse fieldsets get a sensible default.
- **Opt-in optimization:** Clients that want lean payloads must explicitly request fields.

## Common Use Cases

### 1. Minimal Collection Cards (Listing Page)

Request only what's needed to render a card grid:

```
GET /api/v1/public/es/collection-items?fields=id,name,slug,cover_url,category_id
```

Response: ~2–3 KB per item (vs. ~8–10 KB with all fields).

### 2. Full Item Detail Page

Request all fields for a single item's detail page:

```
GET /api/v1/public/es/collection-items/payaso-de-yeso
```

No `?fields=` needed; defaults to `DETAIL_FIELDS`.

### 3. Bulk Fetch for Cache Prewarming

Request only IDs and timestamps for cache invalidation logic:

```
GET /api/v1/public/es/collection-items?fields=id,updated_at
```

Response: ~100 bytes per item.

## Usage from teatromuseo-web

### Scenario: Block Renderer Needs Specific Fields

In a block's ViewModel, specify which fields the block needs:

```php
class CollectionGridViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $items = $this->data['collection_items'] ?? [];
        
        // The block only needs these fields from each item
        $fields = ['id', 'name', 'slug', 'cover_url'];
        
        return [
            'items' => array_map(
                fn ($item) => array_intersect_key($item, array_flip($fields)),
                $items
            ),
        ];
    }
}
```

The canonical `BlockPrefetchService` planner computes these dependencies from
the block projection and sends the right `?fields=` parameter before rendering.

### Scenario: Cache Key Includes Field Selection

When caching API responses, include the field selection in the cache key so different field selections don't collide:

```php
// In WebApiClient or a custom service layer:
$selectedFields = implode(',', ['id', 'name', 'cover_url']);
$cacheKey = 'collection_items_' . md5($url . '|' . $selectedFields);
```

**Note:** `WebApiClient` already does this for `?fields=` parameters via its `md5($url . '|' . locale)` key construction — each URL variant is cached separately.

## Trait API Reference

The `SparseFieldsetTrait` (from `dcardenasl\Ci4ApiCore\Traits\SparseFieldsetTrait`) provides:

### `parseFieldsParam(array $allowedFields): array`

Parses and validates the `?fields=` query parameter.

**Arguments:**
- `$allowedFields` (array) — whitelist of field names the client is allowed to request.

**Returns:** Array of field names the client requested, or all `$allowedFields` if `?fields=` was not sent.

**Example:**
```php
$fields = $this->parseFieldsParam(self::LISTING_FIELDS);
// If request has ?fields=id,name,slug and all are in LISTING_FIELDS, returns ['id', 'name', 'slug']
// If request has no ?fields=, returns all LISTING_FIELDS
// If request has ?fields=id,invalid_field, throws BadRequestException
```

### `sparseFilter($data, array $fields): array`

Filters a single item (DTO, array, or object) to only include specified fields.

**Arguments:**
- `$data` (mixed) — a DTO, array, or stdClass object to filter.
- `$fields` (array) — field names to keep.

**Returns:** Filtered array with only the specified fields.

**Example:**
```php
$item = ['id' => 1, 'name' => 'Payaso', 'description' => 'Long text...'];
$filtered = $this->sparseFilter($item, ['id', 'name']);
// Result: ['id' => 1, 'name' => 'Payaso']
```

## Error Handling

If the client requests a field that's **not in the whitelist**:

```
GET /api/v1/public/es/collection-items?fields=id,password,secret_key
```

The API returns `400 Bad Request`:

```json
{
  "ok": false,
  "status": 400,
  "messages": ["Invalid fields requested: password, secret_key"]
}
```

This prevents:
- Accidental data leakage (fields the schema doesn't expose).
- Probing for hidden fields (attackers can't guess field names by trial and error).

## Performance Impact

### Payload Reduction

- **Listing request with sparse fieldsets:** 40–60% smaller than default.
- **Detail request with sparse fieldsets:** 20–40% smaller than default (detail already includes more fields).

### API Processing Cost

Filtering happens **after** the service layer returns full data, so CPU cost is negligible. The benefit is purely in **reduced network bytes** and **faster rendering** on the client side (less JSON to parse).

### Cache Considerations

Each unique `?fields=` parameter creates a separate cache entry. This is intentional:
- A client fetching `?fields=id,name` gets a different cache key than one fetching `?fields=id,name,description`.
- Both can be cached independently and have separate TTLs.
- Cache invalidation via `CacheInvalidator::deleteMatching()` clears all field variants of a URL scope in one glob.

## Testing Sparse Fieldsets

### Unit Test Example

```php
public function testSparseFieldsFiltersListingResponse()
{
    $this->get('/api/v1/public/es/collection-items?fields=id,name');
    
    $this->assertJsonStringEqualsJsonString(
        json_encode([
            'ok' => true,
            'data' => [
                ['id' => 1, 'name' => 'Item A'],
                ['id' => 2, 'name' => 'Item B'],
            ],
        ]),
        $this->response->getBody()
    );
    
    // Ensure unwanted fields are NOT in the response
    $response = json_decode($this->response->getBody(), true);
    $this->assertArrayNotHasKey('description', $response['data'][0]);
    $this->assertArrayNotHasKey('cover_url', $response['data'][0]);
}
```

### Integration Test Example

```php
public function testInvalidFieldsReturns400()
{
    $this->get('/api/v1/public/es/collection-items?fields=id,malicious_field');
    
    $this->assertResponseCode(400);
    $this->assertJsonStringContainsJsonString(
        json_encode(['ok' => false]),
        $this->response->getBody()
    );
}
```

## Changelog & Migration Notes

### v1.0 (Initial)
- Sparse fieldsets implemented in `SparseFieldsetTrait`.
- Integrated into `teatromuseo-catalog-domain` `PublicCollectionItemController`.

### v1.1 (Planned)
- Event-domain `PublicEventController` support (Task #4).
- CMS-domain public endpoints (if added).
- Analytics on field request patterns to optimize default fieldsets.

---

**Related Documentation:**
- `BLOCK_PREFETCH.md` — pre-render planning and parallel execution.
- `ci4-api-core` `SparseFieldsetTrait` source code.
