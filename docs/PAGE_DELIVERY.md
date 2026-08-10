# PageDelivery

`App\PageDelivery` is the public-page composition seam. Controllers create a
typed `PageDeliveryRequest` and pass the resulting `PageDeliveryResponse` to the
existing renderer; ViewModels and views do not know whether data came from a
synchronous composition or a snapshot.

## Selection policy

- Preview always uses `SynchronousPageDeliveryAdapter` and never reads or writes
  a public snapshot.
- `WEB_PAGE_DELIVERY_MODE=sync` is the controlled composition mode for preview,
  regeneration and local verification.
- `WEB_PAGE_DELIVERY_MODE=snapshot` checks `SnapshotPageDeliveryAdapter` first.
  A snapshot is accepted only when its version, locale, route and complete
  envelope match the request.
- Synchronous fallback is disabled by default. If deliberately enabled, it is
  protected by `RegenerationLockInterface` so a cache miss cannot create one
  composer per visitor.

The feature is disabled by default with `WEB_PAGE_DELIVERY_ENABLED=false` until
the shared snapshot backend and the load budget have been verified. This keeps
the existing public path as the safe rollback while CACHE-02 and QA-03 remain
pending.

## Delivery envelope

Every successful response contains `version`, `data.page`, `data.layout`,
`data.block_context`, `meta` and `source`. Dynamic block results remain keyed by
their instance path (`0`, `1`, `2.0`, ...). Each result carries its type,
configuration, page, limit, filters, order, facets, preview flag and source
state, so two blocks of the same type cannot share an accidental response.

The synchronous adapter reuses the existing `BlockPrefetchService`; it does not
introduce a second SmartPrefetch implementation. Form definitions are also
loaded before `BlockRenderer` begins, so the delivery renderer does not perform
HTTP I/O.

## Snapshot storage

`FileSnapshotStore` is read-only in this phase. CACHE-02 owns the builder,
versioned writes and active-pointer publication. Enable the file adapter only
after verifying that the configured directory is shared by every worker. With
no `WEB_PAGE_SNAPSHOT_DIR`, the application uses `NullSnapshotStore` and never
pretends that a local filesystem is authoritative.
