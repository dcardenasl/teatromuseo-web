<?php

declare(strict_types=1);

namespace App\Services;

class SiteSettingsService extends BaseSiteService
{
    private const CACHE_TTL = 300;

    /**
     * Public reCAPTCHA site key for the given language, or '' when unset.
     *
     * This is the same `settings` read the CMS layout composition uses to
     * decide whether form_embed.php can render the widget (see
     * FormEmbedViewModel::recaptchaSiteKey()) — FormController must check the
     * identical condition before requiring a token, or it can demand a token
     * the visitor was never shown a way to produce.
     */
    public function getRecaptchaSiteKey(string $lang): string
    {
        $settings = $this->fetchData("public/{$lang}/settings", [], self::CACHE_TTL, 'settings');
        $key      = $settings['recaptcha_site_key'] ?? '';

        return is_scalar($key) ? trim((string) $key) : '';
    }
}
