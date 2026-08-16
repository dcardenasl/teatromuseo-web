# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Single BFF client for all public reads** — the three domain-specific clients
  (`webApiClient()`, `catalogWebApiClient()`, `eventWebApiClient()`) are replaced by
  one `bffWebApiClient()` pointed at `BFF_API_BASE_URL`; `BlockPrefetchService` now
  takes one injected client instead of a per-domain map. Analytics writes keep their
  own `WEB_TRACKING_API_BASE_URL` straight to CMS, since tracking is a write and the
  BFF is read-only.

### Added

- **`WebApiClient` reuses its cURL connection/TLS session across sequential
  calls** — every `get()`/`multiGet()`/`multiGetAcross()` handle now attaches
  a per-instance `CURLOPT_SHARE` (connection + SSL session + DNS cache), so
  two calls made moments apart to the same host within one request (e.g.
  `page-bootstrap` then `layout`) reuse the first call's TCP+TLS handshake
  instead of each paying their own.
- **Cold-page round trips cut from 5–7 to 2–3 via composite CMS endpoints** —
  `LayoutDataPrefetchService` and `PageResolverService` (renamed
  `resolveRedirectAndPage()`) now call the CMS domain's new
  `public-read/{locale}/layout` and `public-read/{locale}/page-bootstrap/{path}`
  endpoints (ADR 006) instead of firing 3 and 2 separate requests
  respectively. Under hosting without real request concurrency, round-trip
  count — not batching — is what determines cold-load latency.
  `CacheInvalidator` gained scope aliasing (`settings`/`menus`/`collections` →
  also `layout`; `redirects` → also `pages`) so the composite responses'
  cache entries invalidate correctly.
- **Single block prefetch pipeline** — `BlockPrefetchService` plans collection grids, listings, timelines, and item detail blocks before rendering, routes each request to its owning domain, deduplicates identical queries, and returns explicit path-keyed result envelopes. Legacy overlapping prefetch services were removed.
- **Sparse fieldsets optimization** — clients can request subset of fields via `?fields=id,name,slug` to reduce payloads 40–60%; integrated via `SparseFieldsetTrait` from `ci4-api-core` in domain public APIs.
- **Parallel alias resolution** — `ParallelAliasResolver` batches slug-to-ID lookups across collection items and events, eliminating sequential alias resolution calls.
- **Performance documentation** — added the current `BLOCK_PREFETCH.md` contract, ADR, sparse-fieldset guidance, and pre-render examples.
- **Cache warmup command** — `CacheWarmup` command pre-populates common page caches during deploy to avoid cold-start latency.

- **Configurable listing projections** — public collection grids and listings now consume CMS
  field projections for titles, summaries, dates, images, extra metadata, ordering, and filters.
- **Structured institutional team details** — About-page team cards now render profession,
  primary position, and additional roles from persisted CMS content.
- **Public cache status endpoint** — added a protected endpoint that reports cache backend,
  configuration, last invalidation source, scopes, and deleted-entry counts.

- **Localized public section routes and navigation** — added locale-aware section paths and
  semantic public navigation for events, catalog listings, contact, history, and TeatroEscuela.
- **Domain-backed collection grids and event filters** — collection grids can consume domain
  metadata and public event listings can filter by localized event type.
- **Collection timeline, team, and press media** — added timeline/team content rendering and
  press gallery asset support for public editorial pages.
- **TeatroEscuela activity pages** — added localized activity detail rendering with registration,
  status, instructors, requirements, and video presentation support.
- **Collection listing video playback** — public listing cards can expose YouTube/Vimeo videos
  with poster images, accessible play buttons, and a modal player.

- **Configurable listing presentation** — collection listings support configurable cover aspect
  ratios, numbered pagination, locale-aware dates, and real activity start dates.

- **Localized catalog and event item rendering** — public listing labels now include catalog and event metadata terms, public listing sources normalize external featured images, and catalog/event detail blocks render headers, details, content, and galleries with locale-aware fallbacks.
- **Base web controller & service architecture** — introduced `BasePublicWebController` and `BaseSiteService` to unify page resolution, i18n locale handling, SEO metadata, and API domain consumption across the web frontend.
- **Dynamic public listing page builder** — added `PublicListingPageBuilder` to resolve and render CMS pages of type `events` and `catalog_listing` with dynamic listing blocks and filters.
- **Public Event detail pages & integration** — created `EventController` and `SiteEventService` to render public event detail views (`event_item_header.php`) consumed from `teatromuseo-event-domain`.
- **Refactored museum catalog components** — updated `MuseumController`, `CollectionListingViewModel`, and modular block partials (`catalog_item_header.php`, `catalog_item_gallery.php`), removing legacy monolithic views.
- **`entry_reference` / `related_entries` blocks** — pages and entries can now render cross-links
  to other published entries, with dedicated profile fichas for companies, people, works, videos,
  festivals, exhibitions, courses and publications.
- **`MuseumController` / `/{locale}/museo/coleccion` routes** — public listing and detail pages
  for the museum catalog collection, backed by `SiteCatalogService` against the new
  `teatromuseo-catalog-domain` public API.
- **Cache invalidation scopes** — `CacheInvalidator` now accepts `events`, `categories`,
  `techniques`, and `collection_items` in addition to the existing CMS scopes, so admin writes to
  those resources can purge their public cache entries via the `/cache/invalidate` webhook.
- **Grouped footer navigation** — the vertical footer now renders one labeled column per menu
  group (matching the CMS's grouped navigation seeder) instead of a single flat link list; the
  horizontal layout flattens grouped items back to a single row of leaf links.

### Fixed

- **Menu items without a CMS destination no longer render as broken `#` links** —
  header, footer, and mobile-drawer navigation now render non-clickable items as
  plain text instead of an anchor pointing at `#`; the mobile submenu accordion
  moved off the row's click handler onto its own `aria-expanded`/`aria-controls`
  toggle button so a row that does resolve to a real link stays a genuine anchor.
- **`PageDelivery` ignored active redirects on manifest routes** — `deliverConfiguredPageRoute()`
  never checked `public/redirects/{route}`, so a redirect created for `home`/`events`/`catalog` (or
  any CMS slug added to the manifest) was silently overridden by that route's own content while
  `pageDeliveryEnabled=true`. `SynchronousPageDeliveryAdapter::deliver()` now checks the redirect
  first, with the same precedence the legacy resolver already uses.
- **`PageDelivery` could snapshot an unbounded number of free-text search variants** — `q`,
  `search`, and `filter_value`/`filter_by` on Cartelera/Catalog listings each produced a distinct,
  permanent snapshot identity with no global cap on `FileSnapshotStore`. These variants are no
  longer snapshot-eligible; they always render synchronously, like preview does.
- **Cold-page latency: menu collection-slug resolution issued its own uncached
  request outside the layout prefetch batch** — `LayoutDataPrefetchService`
  fetched `navigation`+`settings` in one parallel `multiGet()`, but menu items
  whose CMS payload omitted `collection_slug` fell back to
  `BaseSiteService::resolveCollectionSlug()`, which issued its own blocking
  `GET public/{locale}/collections` *after* that batch returned — a third,
  strictly sequential round trip on every single page (menus render
  everywhere). `public/{locale}/collections` now joins the initial
  `navigation`/`settings` batch; `WebApiClient` shares the same cache key
  between `get()` and `multiGet()`, so the fallback becomes a cache hit
  instead of a network call. Found auditing a cold `/es/nosotros` load
  (`docs/audits/2026-08-13-auditoria-carga-fria-web-domains.md`), a page with
  no dynamic blocks of its own — the menu was effectively the page's entire
  network cost.
- **Broken `/files/{id}/view` media fallback** — block view models no longer fabricate a
  local `/files/{id}/view` URL when a hub-file reference has no resolved URL; the CMS domain
  now always resolves the real public URL upstream, so a missing one means the asset should
  render as absent instead of a broken link. Cache schema bumped to invalidate previously
  cached responses that may still contain the removed fallback.
- **CSP blocked every block-scoped inline style in production** — `Config\ContentSecurityPolicy`
  shipped `styleSrc`/`styleSrcElem`/`styleSrcAttr` at `'self'` with no exception, but block
  partials (`hero_slider`, `hero_banner`, `cards_slider`, `timeline`, `gallery_item`, `footer`,
  ...) each render their own `<style>` block and CMS-editor-controlled `style="..."` attributes
  (overlay/text colors, heights, animation delays) by design. With `app.CSPEnabled` true in
  production, the browser silently dropped all of it. Added `'unsafe-inline'` to the three style
  directives — nonces don't fit this architecture (blocks render/cache independently, with no
  single per-request nonce to thread through them, and style *attributes* can't carry a nonce at
  all).
- **Cache invalidation observability** — invalidation requests now retain their source and
  operational status so automatic and manual cleanups can be distinguished.

- **Nested block and media rendering** — nested layouts, gallery images, and hero media now
  resolve consistently without placeholder substitution or broken hero links.
- **Localized public details and slugs** — detail dates, routes, accented slugs, and public
  content localization now use the correct locale and deterministic normalization.
- **Footer, social links, and tracking requests** — public footer/social configuration is now
  respected and tracking requests authenticate correctly.

- **Default public base URL port** — corrected the default/example `app.baseURL` and every
  reference in docs, `init.sh`, and tests from `8186` (the totem app's port) to `8184`.
- **`CmsCollectionSource` facets** — `facets()` called the non-existent
  `SiteCategoryService::listCategories()` / `SiteTagService::listTags()`; both services only
  expose `list($lang, $collectionKey)`, so requesting category/tag facets on a CMS collection
  listing crashed instead of rendering.
- **`CollectionListingViewModel`** — resolving a listing source with a missing context
  collaborator (e.g. a view model built without going through `BlockRenderer`) threw a
  `TypeError` instead of rendering an empty/invalid listing.
- **Detail and gallery block labels** — catalog/event detail and gallery block headings were
  hardcoded in Spanish; they now use `lang()` keys and render correctly in every active locale.
- **`CollectionListingViewModel`** — a collection's curated `default_meta_description` now takes
  priority over its on-page intro text for the listing page's SEO description.

- **Placeholder media and hero links** — real entries without media no longer receive stock
  placeholders, and empty hero destinations are not rendered as broken links.

### Security

- **`blocks/preview` had no authentication of its own** — the server-to-server proxy called by the
  Admin panel's block editor relied only on `throttle:10,60`. Added an optional shared-secret check
  (`BLOCK_PREVIEW_KEY`, `hash_equals()` against `X-Block-Preview-Key`, same pattern as
  `CacheController`): while unset the endpoint keeps its previous throttle-only behavior, and when
  set a missing or incorrect header is rejected with `401` (2026-08-12 audit finding).
- **CSRF-safe public forms under page caching** — public form submissions previously embedded
  their CSRF token via `csrf_field()` directly in HTML that `pagecache` could serve to every
  visitor, and form routes relied only on that embedded token rather than an explicit filter.
  `CsrfCookieFilter` now mirrors a fresh, per-client token into a readable cookie after
  `pagecache` runs, form routes require the `csrf` filter explicitly, and public forms submit via
  `fetch` with the token read from that cookie instead of from cached markup. `Cookie.secure` and
  the CSRF cookie/token names are also production-hardened (`__Host-` prefix, environment-gated
  `secure` flag).

### Changed

- **`BlockPrefetchService` split into single-responsibility collaborators** —
  the 1223-line class (block-tree walking, request planning, query building,
  request execution, dependency resolution, and result materialization all
  in one file) is now a 168-line facade over 8 focused classes under
  `app/Services/BlockPrefetch/`: `BlockPlanCollector`, `ListQueryBuilder`,
  `PrefetchRequestQueue`, `PrefetchRequestExecutor`, `BlockRequestPlanner`,
  `BlockDependencyResolver`, `BlockResultMaterializer`, and
  `RequestQueryReader`. The by-reference `array &$requests, array
  &$requestIndexes` parameter pair threaded through half the original
  methods is replaced by `PrefetchRequestQueue`, a real object; the mutable
  `$this->planningLocale` instance field (a latent hazard on a
  request-shared singleton service) is replaced by locale passed into a
  fresh `PrefetchRequestQueue` per call. Public API unchanged
  (`prefetchContext()`, `prefetch()`, constructor `array $clients`) — no
  consumer needed changes. No behavior change: the original
  `BlockPrefetchServiceTest` suite passes unmodified.
- **`SiteCollectionService` cache TTL** — extended from 10 minutes to 1 hour to reduce upstream
  request volume under shared-hosting process limits; edits still invalidate immediately via
  `CacheInvalidator` regardless of TTL.
- **Public listing and download presentation** — listing controls and card metadata follow the
  configured projection, while shared buttons and document-download actions use the refined site
  component styling.

- **Institutional team rendering** — the public About page now reads editable team-member
  children and persisted CMS content instead of legacy hardcoded staff and page overrides.
