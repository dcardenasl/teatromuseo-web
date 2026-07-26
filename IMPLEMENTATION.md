# Implementation Log - ci4-website-builder-web

This document tracks the implementation of each component.

## 1. WebApiClient Library
File: `app/Libraries/WebApiClient.php`
- HTTP client for communicating with ci4-website-builder-domain API
- Built-in caching with configurable TTL
- Handles API key authentication via X-App-Key header
- Automatic Accept-Language header based on current locale
- Normalizes API responses to standard format

## 2. BasePublicWebController
File: `app/Controllers/BasePublicWebController.php`
- Base controller for all public-facing controllers
- Pre-loads menus (main-nav, footer) and settings into every view
- Provides `render()` method with automatic SEO data injection
- Provides `notFound()` helper for 404 responses

## 3. Services Factory
File: `app/Config/Services.php`
- Singleton service container for all site services
- Provides shared instances with dependency injection
- Factory methods: webApiClient, siteSettingsService, siteMenuService, sitePageService, siteCollectionService, siteEntryService, siteRedirectService, blockRenderer

## 4. App Configuration
File: `app/Config/App.php`
- Adds webApiBaseUrl (default: http://localhost:8190)
- Adds webApiKey (default: web_api_test_key)
- Sets default locale to 'es'
- Configures baseURL to http://localhost:8186/

## 5. Site Services Layer
Implements 6 API adapter services with automatic caching:

### SiteSettingsService
File: `app/Services/SiteSettingsService.php`
- Fetches public settings from GET /api/v1/public/settings
- Cache TTL: 3600 seconds (1 hour)
- Returns key-value array of all public settings

### SiteMenuService
File: `app/Services/SiteMenuService.php`
- Fetches menu trees from GET /api/v1/public/menus/{menuKey}
- Cache TTL: 600 seconds (10 minutes)
- Returns hierarchical menu structure with children

### SitePageService
File: `app/Services/SitePageService.php`
- Fetches individual pages: GET /api/v1/public/{lang}/pages/{slug}
- Lists all pages: GET /api/v1/public/{lang}/pages
- Cache TTL: 300-600 seconds
- Used for resolving page slugs and sitemap generation

### SiteCollectionService
File: `app/Services/SiteCollectionService.php`
- Fetches all collections: GET /api/v1/public/{lang}/collections
- Matches by translated collection slug for routing
- Cache TTL: 600 seconds

### SiteEntryService
File: `app/Services/SiteEntryService.php`
- Lists entries in collection: GET /api/v1/public/{lang}/entries/{collectionKey}
- Fetches single entry: GET /api/v1/public/{lang}/entries/{collectionKey}/{slug}
- Cache TTL: 180-300 seconds

### SiteRedirectService
File: `app/Services/SiteRedirectService.php`
- Resolves redirects: GET /api/v1/public/redirects/{path}
- Cache TTL: 3600 seconds (very stable)

## 6. BlockRenderer Library
File: `app/Libraries/BlockRenderer.php`
- Recursively renders blocks by block_key
- Automatically selects view: blocks/{block_key}.php
- Falls back to blocks/unknown.php if view not found
- Passes context: $block, $config, $data, $renderedChildren to each view
- Supports nested/hierarchical block structures

## 7. Block Views
Directory: `app/Views/blocks/`
Implemented block types:
- `rich_text.php` - Renders HTML content with prose styling
- `image.php` - Renders images with alt text and captions
- `hero_banner.php` - Full-width hero section with CTA
- `cta.php` - Call-to-action button/section
- `container.php` - Wrapper div with custom CSS classes
- `unknown.php` - Fallback for missing block types (dev vs prod modes)

## 8. Layout System
Master layout: `app/Views/layouts/public.php`
- Loads all partials
- Injects pre-loaded menus and settings
- Renders main content view in <main> tag

### Partials

#### `head.php`
- Dynamic <title> tag
- Meta description from page or settings
- Meta robots (index, follow, etc.)
- Canonical URL
- Open Graph tags (og:image, og:title, og:description)
- JSON-LD schema data

#### `header.php`
- Navigation bar with logo and menu
- Recursive menu item rendering
- Support for dropdown submenus
- CSS classes from settings

#### `footer.php`
- Company info from settings
- Footer menu links
- Social media links from settings
- Copyright notice
- 3-column layout (info, menu, social)

#### `flash_messages.php`
- Flash message display (success, error, warning)
- Auto-dismiss notifications
- Tailwind styling

## 9. Content Views

### `page.php`
- Generic CMS page template
- Displays title, excerpt, rendered blocks
- SEO metadata passed from controller

### `collection/index.php`
- Collection listing view
- Grid layout for entries
- Pagination support
- Collection title and intro from settings

### `collection/show.php`
- Single entry detail view
- Entry title, metadata (date, categories, tags)
- Rendered blocks
- Back link to collection listing

## 10. Controllers

### PageController
File: `app/Controllers/PageController.php`
Implements 5-step page resolution algorithm:

1. **CMS Page Resolution**: Try to fetch page by slug
   - GET /api/v1/public/{lang}/pages/{slug}

2. **Exact Collection Match**: Match collection by translated slug
   - GET /api/v1/public/{lang}/collections
   - Match the translated collection slug exactly

3. **Collection Entry Resolution**: Try to fetch entry from collection
   - Split path: [prefix, slug]
   - GET /api/v1/public/{lang}/entries/{collectionKey}/{slug}

4. **Redirect Resolution**: Try to find a redirect rule
   - GET /api/v1/public/redirects/{path}
   - Return 301/302 redirect if found

5. **404 Fallback**: Return not found error
   - Render 404 page with 404 HTTP status

SEO Data Extraction:
- Extracts meta_title, meta_description, canonical_url, og_image, robots, schema_data
- Falls back to page title or excerpt if meta fields missing
- Passes to layout for <head> tag rendering

### SitemapController
File: `app/Controllers/SitemapController.php`
- Generates XML sitemap for SEO
- Includes all published pages
- Includes all collection entries
- Uses sitemap_priority and sitemap_changefreq from API
- Caches generated XML (3600 seconds)
- Returns Content-Type: application/xml
- For a comprehensive guide on the database mappings, caching strategies, and tests, see the [SEO & Sitemaps Architecture Guide](file:///Users/davidcardenas/Developer/PHP/ci4-website-starter/docs/SEO_SITEMAP_ARCHITECTURE.md).

## 11. Routing Configuration
File: `app/Config/Routes.php`
- GET / → PageController::home (homepage)
- GET /sitemap.xml → SitemapController::index (sitemap)
- GET (:any) → PageController::resolve/{path} (catch-all dynamic resolver)

## 12. Frontend Configuration

### Tailwind CSS
Files: `tailwind.config.js`, `postcss.config.js`, `public/assets/css/app.css`
- Configured for content scanning in app/Views/**/*.php
- PostCSS processing for vendor prefixes
- Development and production build scripts in package.json

### NPM Scripts
- `npm run dev:css` - Watch mode for Tailwind compilation
- `npm run build:css` - Production minified CSS build

## 13. Environment Configuration
File: `.env`
- CI_ENVIRONMENT = development
- app.baseURL = http://localhost:8186/
- app.defaultLocale = es
- WEB_API_BASE_URL = http://localhost:8190
- WEB_API_KEY = web_api_test_key
- cache.handler = file

## 14. Quality Configuration
Files copied from teatromuseo-web-ci4:
- `.php-cs-fixer.dist.php` - PHP code style fixing
- `phpstan.neon` - Static analysis configuration (level 8)
- `phpunit.xml.dist` - Unit test configuration
- `phpstan-bootstrap.php` - PHPStan bootstrap
- `preload.php` - Preload optimization

## 15. Documentation
File: `CLAUDE.md`
- Project overview and architecture
- Quick start guide
- Key files and their purposes
- Configuration reference
- Page resolution algorithm explanation
- Service layer documentation
- Block rendering system details
- Troubleshooting guide
