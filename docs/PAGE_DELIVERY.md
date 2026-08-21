# PageDelivery

`App\PageDelivery` is the public-page composition seam. Controllers create a
typed `PageDeliveryRequest` and pass the resulting `PageDeliveryResponse` to the
existing renderer; ViewModels and views do not know whether data came from a
synchronous composition or a snapshot.

## Selection policy

- Preview always uses `SynchronousPageDeliveryAdapter` and never reads or writes
  a public snapshot.
- In production, the default is `snapshot`; a shared snapshot backend and a
  completed warm-up are therefore part of the hosting deployment, not an
  optional performance tweak for visitor traffic.
- `WEB_PAGE_DELIVERY_MODE=sync` is the controlled composition mode for preview,
  regeneration and local verification.
- `WEB_PAGE_DELIVERY_MODE=snapshot` checks `SnapshotPageDeliveryAdapter` first.
  A snapshot is accepted only when its version, locale, route and complete
  envelope match the request.
- The Web has no local resolver fallback. Every localized public path is
  resolved by the BFF's `page-resolve` endpoint. Only routes explicitly present
  in `WEB_PAGE_SNAPSHOT_MANIFEST_ROUTES` can use snapshots; all other routes are
  synchronous BFF requests and cannot create an unbounded snapshot store.
- Snapshot eligibility is canonical-only: a request with a query variant is
  synchronous even when the parameter looks bounded. Enabling a variant
  requires a route-specific allow-list, bounded values, explicit warm-up
  coverage and matching invalidation scopes. This prevents a new filter or
  pagination value from silently multiplying files on the shared hosting
  filesystem.

## Delivery envelope

Every successful response contains `version`, `data.page`, `data.layout`,
`data.block_context`, `meta` and `source`. Dynamic block results remain keyed by
their instance path (`0`, `1`, `2.0`, ...). Each result carries its type,
configuration, page, limit, filters, order, facets, preview flag and source
state, so two blocks of the same type cannot share an accidental response.

## Localized detail URLs

Domain detail readers expose `slugs` as a map of raw item slugs keyed by locale.
The detail projection always loads the complete map, including when the caller
uses the default `fields=[]` projection; list projections remain locale-scoped
unless they explicitly request the complete `slugs` field:

```json
{"es":"pieza-es","en":"piece-en","fr":"piece-fr"}
```

The BFF `PageResolver` turns that map into `page.localized_slugs`, prefixing
each value with the locale-specific public route. For example, a French event
detail is represented as `programmation/piece-fr`, while a French catalog
detail is represented as `musee/collection/piece-fr`.

Web owns the final visitor-facing route map in `App\Support\PublicPaths` and
rebuilds domain-detail language links from the configured per-locale item slug.
This deliberately prevents a stale route prefix from one locale from leaking
into another. If a locale has no configured item slug, Web uses the current
detail slug only as a compatibility fallback; the BFF detail contract remains
the authoritative source for published localized slugs.

The versioned route matrix lives in
`docs/contracts/public-routes.json`. The BFF keeps a local adapter for its
incoming resolver, and the two exports are compared in both repositories'
CI. No runtime package or HTTP dependency is introduced for this static
policy.

The synchronous adapter is intentionally a thin BFF client. It maps the
complete page envelope and never performs Web-side domain reads or block
composition.

## Snapshot storage

`FileSnapshotStore` is the shared filesystem backend for CACHE-01/02. It stores
versioned objects under `objects/`, an atomically published active pointer under
`pointers/`, and invalidation markers under `invalidations/`. Both the object and
the pointer are written to temporary files in the same directory and published
with `rename()`. Readers follow only the active pointer, so a failed builder
cannot replace the previous snapshot with a partial artifact.

The backend is enabled only when both `WEB_PAGE_SNAPSHOT_DIR` and
`WEB_PAGE_SNAPSHOT_SHARED=true` are configured. The second setting is an explicit
deployment acknowledgement that all workers see the same filesystem. Without
it, `NullSnapshotStore` is used. APCu is process-local and is never authoritative
for snapshots. JSON can be stored as `gzip` (default) or `none`; maximum bytes,
retention, stale TTL, and lock recovery age are bounded by configuration.

`SnapshotBuilder` owns the single-flight boundary. It acquires one filesystem
lock per normalized page identity before composing, validates the response
envelope and identity, captures a deterministic source revision, and publishes
the new pointer only after the complete object is present. A stale lock is
recoverable after `WEB_PAGE_SNAPSHOT_LOCK_TTL`; a competing builder returns
`busy` and leaves the active pointer untouched.

Invalidation writes a marker instead of deleting the active pointer. The marker
can be scoped by source scope and optionally by locale/route; the current
snapshot is then served as `stale` within the stale window while the next
controlled builder or warm-up replaces it. This preserves a working response
during an upstream outage and prevents an invalidation from causing a cold
request storm. The webhook records the invalidation source and returns the
number of affected snapshot identities.

The default warm-up manifest contains the stable `home`, `events` and `catalog`
route keys. `WEB_PAGE_SNAPSHOT_MANIFEST_ROUTES` may replace that list with an
explicit deployment allow-list; it must include every high-traffic public route
that should be snapshot-served.

`php spark cache:warmup` iterates the list serially, never crawls URLs generated
by content, and writes a bounded JSON report. In production snapshot mode the
command is strict by default: a disabled shared backend or a failed route
returns a non-zero exit code and does not claim success. Use `--strict` to apply
the same fail-closed behavior explicitly in staging. In local/synchronous mode
the command may still compose the routes through the BFF seam for verification,
but that is a composition check, not snapshot publication. Visitor requests
never rebuild a stale snapshot; cron is the only production regeneration path.
`start-dev.sh` starts the applications but does not run this command
automatically; invoke it after the stack is ready when a cold local cache is
part of the test.

## Publication invalidation

CMS, Catalog and Events record public-data changes in a local
`cache_invalidation_outbox` in the same database transaction as the write. A
rollback therefore removes the event, and an unavailable Web app cannot make a
content write perform a synchronous remote request. Each domain exposes:

```text
php spark cache:dispatch-outbox --limit 20
```

Run that command from cron at a bounded interval. It claims events with a lease,
delivers the scope/locale/route invalidation to Web, acknowledges successful
delivery and retries failures with bounded backoff. The dispatcher is safe to
run from more than one worker; the Web endpoint only marks affected snapshots
stale and never removes an unrelated active pointer.
