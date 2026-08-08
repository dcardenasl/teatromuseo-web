# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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

### Changed

- **`SiteCollectionService` cache TTL** — extended from 10 minutes to 1 hour to reduce upstream
  request volume under shared-hosting process limits; edits still invalidate immediately via
  `CacheInvalidator` regardless of TTL.
- **Public listing and download presentation** — listing controls and card metadata follow the
  configured projection, while shared buttons and document-download actions use the refined site
  component styling.

- **Institutional team rendering** — the public About page now reads editable team-member
  children and persisted CMS content instead of legacy hardcoded staff and page overrides.
