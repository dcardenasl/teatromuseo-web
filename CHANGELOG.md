# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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
