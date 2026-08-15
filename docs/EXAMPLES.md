# Public page delivery examples

The Web receives a complete BFF page envelope and passes its already-composed
block context to `BlockRenderer`:

```php
$delivery = $pageDelivery->deliver($request);
$html = Services::blockRenderer()->render(
    $delivery->page['blocks'] ?? [],
    $request->locale,
    $delivery->blockContext,
);
```

The BFF owns source routing, query normalization, deduplication, caching and
partial-failure envelopes. ViewModels only normalize presentation data. They
must not create domain clients or perform HTTP during rendering.

See [PageDelivery](PAGE_DELIVERY.md) and [ADR 008](adr/008-bff-full-page-resolution.md).
