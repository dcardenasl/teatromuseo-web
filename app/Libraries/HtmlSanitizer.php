<?php

declare(strict_types=1);

namespace App\Libraries;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Second-layer sanitizer for rich-text HTML rendered on the public site.
 *
 * The Domain CMS already sanitizes block content on write
 * (App\Libraries\Cms\HtmlSanitizer in ci4-website-builder-domain). This mirror
 * provides defense-in-depth so the public website never renders unsanitized
 * markup even if content reaches it from a source that bypassed the Domain
 * sanitizer (import, migration, or a future writer). The allowlist is kept
 * intentionally aligned with the Domain sanitizer.
 *
 * The purifier instance is created once per process (singleton pattern).
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        return self::getPurifier()->purify($html);
    }

    private static function getPurifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();

        $cacheDir = WRITEPATH . 'htmlpurifier';
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);

        // Enable ID attribute support
        $config->set('Attr.EnableID', true);

        // Allowed elements — kept aligned with the Domain CMS sanitizer.
        $config->set('HTML.Allowed', implode(',', [
            'div[id|class]',
            'p[id|class]', 'br',
            'b', 'strong', 'i', 'em', 'u', 's', 'small',
            'ul[id|class]', 'ol[id|class]', 'li[id|class]',
            'blockquote', 'pre', 'code',
            'h2[id|class]', 'h3[id|class]', 'h4[id|class]',
            'a[href|title|target|rel|id|class]',
            'img[src|alt|width|height|id|class]',
            'hr',
        ]));

        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('URI.SafeIframeRegexp', null);

        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('HTML.TargetNoopener', true);

        self::$purifier = new HTMLPurifier($config);

        return self::$purifier;
    }
}
