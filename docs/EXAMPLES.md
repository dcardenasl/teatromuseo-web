# Block prefetch examples

These examples use the current single planner. The CMS supplies block
definitions; the web application resolves the referenced domain data before
rendering.

## A page with three sources

```php
$context = Services::blockPrefetchService()->prefetchContext($page['blocks'], $lang);
$html = Services::blockRenderer()->render($page['blocks'], $lang, $context);
```

The planner routes each block by ownership:

```text
0 collection_grid      source_type=event_items  → Event
1 collection_listing   source_type=catalog_items → Catalog
2 collection_timeline  source_type=cms_collection → CMS
```

Native clients execute these requests in one shared `curl_multi` batch, even
when the base URLs differ. The result is addressed by block path:

```php
$context['block_prefetch']['1'] = [
    'ok' => true,
    'status' => 200,
    'data' => $items,
    'meta' => ['pagination' => $pagination],
    'facets' => ['categories' => $categories, 'tags' => []],
    'collection' => null,
    'stale' => false,
    'messages' => [],
];
```

## Query-state example

For a request such as:

```text
/es/museo/coleccion?page=2&q=azul&category=pintura&order_by=name&order_direction=asc
```

`collection_listing` includes the normalized page, search, category, public
ordering, filters, projection fields, locale, and preview credentials (when
present) in its upstream query and cache identity. Page 2 is therefore never
deduplicated with page 1.

## Partial failure example

```php
$result = $context['block_prefetch']['0'];

if (! $result['ok']) {
    // The ViewModel receives an explicit empty result and does not retry HTTP.
    // 4xx responses stay authoritative; transport/5xx may contain stale data.
}
```

This keeps the page valid when one domain is unavailable and makes the failure
observable through the envelope's status and messages.

## Tests that protect the contract

The unit and feature suites cover:

- routing CMS, Catalog, and Event blocks to their owning clients;
- `collection_listing` entries, facets, filters, pagination, and nested paths;
- exact request deduplication while preserving distinct query variants;
- explicit failure envelopes and no ViewModel fallback after prefetch;
- valid public HTML when a domain returns an error.

See [`BLOCK_PREFETCH.md`](BLOCK_PREFETCH.md) and [ADR 0003](adr/0003-single-block-prefetch-before-render.md)
for the complete contract.
