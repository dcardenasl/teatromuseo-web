# Social Media Links Configuration

Complete guide to configuring social media links in the footer.

## Overview

Social media links are managed through the **SocialLinksService** with automatic validation. Empty, invalid, or placeholder URLs are automatically hidden from the footer.

## Supported Networks

The system supports 3 social networks (displayed in this order):

1. **Facebook** (`social_facebook`)
2. **Instagram** (`social_instagram`)
3. **YouTube** (`social_youtube`)

## Configuration Methods

### Method 1: Admin UI (Recommended)

Edit settings directly in the admin panel:

1. Log in to the admin panel
2. Navigate to Settings → Identity/Social
3. Update the social media URLs
4. Save changes

Changes appear immediately (after cache refresh).

### Method 2: .env File

Update `.env` in the web application:

```bash
# Web/.env

SOCIAL_FACEBOOK=https://facebook.com/yourpage
SOCIAL_INSTAGRAM=https://instagram.com/yourprofile
SOCIAL_YOUTUBE=https://youtube.com/@yourchannel
```

Then sync to the API:

```bash
php spark social:sync
```

This will show what will be synced. To actually sync to the API, the command would POST to the Domain API settings endpoint (currently shows as dry-run).

### Method 3: Database Direct

Edit the `cms_settings` table in Domain database:

```sql
UPDATE cms_settings 
SET setting_value = 'https://facebook.com/yourpage'
WHERE setting_key = 'social_facebook' AND is_public = 1;
```

Then clear cache:

```bash
php spark cache:clear
```

## Validation Rules

URLs are validated with these rules:

- ✅ Must start with `http://` or `https://`
- ✅ Must be a valid URL format (per `FILTER_VALIDATE_URL`)
- ❌ Empty values are hidden
- ❌ Placeholders like `[SOCIAL_FACEBOOK_URL]` are hidden
- ❌ Invalid URLs are logged but hidden

## Footer Display

The footer displays active social links using the `SocialLinksService`:

```php
<?php
$socialLinks = \Config\Services::socialLinksService()->getActiveLinks();
?>
<?php foreach ($socialLinks as $link): ?>
    <a href="<?= esc($link['url']) ?>">
        <?= esc($link['label']) ?>
    </a>
<?php endforeach; ?>
```

Only links with valid URLs appear. No placeholder links show in production.

## Caching

Social links are cached for **1 hour** via the `SitesettingsService`:

```php
private const CACHE_TTL = 3600; // 1 hour
```

To refresh immediately:

```bash
php spark cache:clear
```

## Commands

### Show Configuration

View current .env configuration:

```bash
php spark social:sync --show
```

Example output:

```
📱 Social Media Configuration from .env:

✅ SOCIAL_FACEBOOK         https://facebook.com/yourpage
✅ SOCIAL_INSTAGRAM        https://instagram.com/yourprofile
✅ SOCIAL_YOUTUBE          https://youtube.com/@yourchannel
```

### Sync from .env

```bash
php spark social:sync
```

This would sync .env values to the Domain API (currently shows dry-run).

## Architecture

### Files Involved

| File | Purpose |
|------|---------|
| `app/Services/SocialLinksService.php` | Core service with validation & retrieval |
| `app/Commands/SyncSocialLinks.php` | CLI command to sync from .env |
| `app/Views/layouts/partials/footer.php` | Footer display (uses service) |
| `.env` | Environment configuration |
| `../ci4-website-builder-domain/app/Database/Seeds/SiteSocialLinksSeeder.php` | Domain: Seeds settings table |

### Data Flow

```
.env (Web)
   ↓
SocialLinksService (validates URLs)
   ↓
getActiveLinks() (returns only valid URLs)
   ↓
footer.php (displays links)
```

## Adding New Networks

To add a new social network:

1. **Update Domain Seeder** (`SiteSocialLinksSeeder.php`):
   ```php
   [
       'setting_key'     => 'social_newnetwork',
       'setting_value'   => '[SOCIAL_NEWNETWORK_URL]',
       'setting_type'    => 'string',
       'input_type'      => 'url',
       'setting_group'   => 'social',
       'description'     => 'URL for New Network',
       // ...
   ]
   ```

2. **Update Service** (`SocialLinksService.php`):
   ```php
   ['key' => 'social_newnetwork', 'label' => 'New Network', 'domain' => 'newnetwork.com'],
   ```

3. **Update .env.example**:
   ```
   SOCIAL_NEWNETWORK=
   ```

4. **Run Seeder** (Domain):
   ```bash
   php spark db:seed SiteSocialLinksSeeder
   php spark cache:clear
   ```

5. **Footer automatically updates** — no code changes needed.

## Troubleshooting

### Links Not Appearing

1. Check `.env` values are valid URLs (start with `http://` or `https://`)
2. Clear cache: `php spark cache:clear`
3. Verify settings in admin UI or database
4. Check browser console for errors

### Cached Old Values

Clear all caches:

```bash
php spark cache:clear
# Also clear .env-cached values if using APCu
php -r "apcu_clear_cache();"
```

## Best Practices

- ✅ Use full URLs: `https://facebook.com/yourpage` (not just `yourpage`)
- ✅ Use HTTPS URLs when possible
- ✅ Leave empty if network not used (footer will hide automatically)
- ✅ Test links before deploying to production
- ✅ Use admin UI for frequent changes, .env for deployment config

## Examples

### Complete .env Configuration

```bash
SOCIAL_FACEBOOK=https://facebook.com/mycompany
SOCIAL_INSTAGRAM=https://instagram.com/mycompany
SOCIAL_YOUTUBE=https://youtube.com/@mycompany
```

### Partial Configuration

```bash
SOCIAL_FACEBOOK=https://facebook.com/mycompany
SOCIAL_INSTAGRAM=https://instagram.com/mycompany
# Others left empty — will be hidden
```

Result: Only Facebook and Instagram links show in footer.

## Security

- All URLs are escaped before output: `esc($link['url'])`
- Invalid URLs are rejected before display
- No user input — only settings/env vars
- URLs validated with `FILTER_VALIDATE_URL`

---

**Last Updated**: 2026-08-05
**System**: CI4 Website Builder Web
