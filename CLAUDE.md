# CLAUDE.md - ci4-website-builder-web

Public CI4 website for the Website Builder monorepo. It renders CMS content from
`ci4-website-builder-domain` and keeps controllers thin, API access centralized,
block presentation normalized through ViewModels, and JS source separate from
generated browser assets.

## Quick Start

```bash
cd /Users/davidcardenas/Developer/PHP/ci4-website-starter/ci4-website-builder-web

php spark serve --port 8184
npm run dev:css
npm run dev:js
```

Domain should run on port `8190` with a matching public API key.

## Quality Gates

```bash
composer test
composer test:unit
composer test:feature
composer analyse
composer format:check
composer quality

npm run lint:js
npm run build:all
```

`phpstan.neon` runs at level 8 and includes `phpstan-baseline.neon`. The baseline
is decreasing-only: fix or remove entries when touching nearby code; do not add
new debt unless an explicit migration step requires it.

The root `pre-commit` hook runs PHP formatting checks and PHPStan. Husky runs
`lint-staged` for frontend files. Both hooks should remain installed.

## Runtime Configuration

Key environment variables:

- `app.baseURL=http://localhost:8184/`
- `app.defaultLocale=es`
- `WEB_API_BASE_URL=http://localhost:8190`
- `WEB_API_KEY=web_api_test_key`
- `WEB_API_TIMEOUT=5`
- `WEB_API_STALE_TTL=86400`
- `CACHE_INVALIDATE_KEY=<strong-secret>`
- `cache.handler=file`

`CACHE_INVALIDATE_KEY` is mandatory in production. The cache invalidation
endpoint returns `500` when it is unset, `401` for bad keys, and `422` for empty
scope lists.

`app.defaultLocale` must match an active CMS language and a localized `home`
page. It is static CI4 routing configuration, not discovered from Domain.

## Architecture

- Controllers stay thin and call `Config\Services`.
- `PageController::resolve()` resolves dynamic paths in this order:
  collection prefix/index, collection entry, CMS page, redirect, 404.
- `FormController` validates required/email fields, honeypot, and required
  CAPTCHA tokens before submitting to Domain.
- Public POST routes (`forms/*/submit`, `cache/invalidate`) use
  `throttle:10,60`. GET pages are not throttled, so crawlers are not penalized.

## API Client And Services

`app/Libraries/WebApiClientInterface.php` is the test seam for Domain access.
`WebApiClient` implements it with:

- normalized envelopes: `{ok, status, data, meta, messages}`;
- configurable timeout via `WEB_API_TIMEOUT`;
- fresh cache keys: `web_api_v{N}_{scope}_{md5}`;
- stale cache keys: `web_api_stale_v{N}_{scope}_{md5}`;
- stale fallback only for transport failures (`status 0`) and upstream `5xx`.

Do not serve stale data for `4xx` responses. A `404` from Domain is a valid
negative answer.

`app/Services/BaseSiteService.php` owns the shared constructor and
`fetchData()` pattern. Site services type-hint `WebApiClientInterface`, return
arrays/null, and do not throw for upstream failures.

In tests, inject a fake client with:

```php
Services::injectMock('webApiClient', $fake);
```

## Cache Invalidation

`CacheInvalidator` accepts known scopes only and deletes keys matching
`web_api_*_{scope}_*`, which purges fresh and stale entries together.
Current public scopes include `events`, `collection_items`, `categories`, and
`techniques` in addition to the CMS scopes.

Webhook:

```http
POST /cache/invalidate
X-Invalidate-Key: <CACHE_INVALIDATE_KEY>
Content-Type: application/json

{"scopes":["pages","entries","events","collection_items","categories","techniques"]}
```

## Block Rendering

`BlockRenderer` passes base data (`block`, `config`, `data`,
`renderedChildren`) to `app/Views/blocks/{block_key}.php`. Complex blocks use
ViewModels from `app/ViewModels/Blocks`.

Current mapped ViewModels:

- `hero_slider` -> `HeroSliderViewModel`
- `cards_slider` -> `CardsSliderViewModel`
- `form_embed` -> `FormEmbedViewModel`
- `video_player` -> `VideoPlayerViewModel`
- `collection_grid` -> `CollectionGridViewModel`
- `collection_listing` -> `CollectionListingViewModel`
- `metrics_grid` -> `MetricsGridViewModel`
- `cta` -> `CtaViewModel`
- `hero_banner` -> `HeroBannerViewModel`
- `asset_showcase` -> `AssetShowcaseViewModel`
- `social_links` -> `SocialLinksViewModel`

To add one:

1. Create `app/ViewModels/Blocks/{Name}ViewModel.php`.
2. Extend `AbstractBlockViewModel` and return prepared variables from `vars()`.
3. Add the block key to `BlockRenderer::VIEW_MODELS`.
4. Keep the view focused on escaping and markup.
5. Add unit tests for full data, empty data, invalid config, and URL parsing
   helpers when relevant.

`block_text_content()` reads the canonical `content` key only. The earlier
`body`/`html`/`text` legacy fallback (and its usage-counter instrumentation)
was fully removed 2026-07-18 once the counters confirmed zero legacy reads in
real content — see `TASKS.md` DEBT-002 and `../CONTEXT.md`. Do not
reintroduce a multi-key fallback here.

## Frontend Assets

Edit JS in `src/js/`. The browser asset
`public/assets/js/site.js` is generated and committed because the layout
versions it with `filemtime()`.

Commands:

```bash
npm run dev:js
npm run build:js
npm run lint:js
npm run build:all
```

Generated `site.js` should keep its banner:

```js
/* Generated from src/js — do not edit directly. Run: npm run build:js */
```

## Manual Smoke Checks

With Domain on `8190` and Web on `8184`, verify:

- localized home page;
- collection index;
- collection entry;
- form submission;
- cache invalidation with a valid key;
- stale cache fallback: load a cached page, stop Domain, reload, confirm page
  still renders and a warning is logged.

## Common Pitfalls

- Do not add `Modules/`; this repo intentionally stays app-structured.
- Do not put heavy normalization logic back into block views.
- Do not edit generated JS without updating `src/js/` and rebuilding.
- Do not mask Domain `404` responses with stale cache.
- Do not loosen PHPStan or grow the baseline as a shortcut.
