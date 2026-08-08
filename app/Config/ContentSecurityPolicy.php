<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Stores the default settings for the ContentSecurityPolicy, if you
 * choose to use it. The values here will be read in and set as defaults
 * for the site. If needed, they can be overridden on a page-by-page basis.
 *
 * Suggested reference for explanations:
 *
 * @see https://www.html5rocks.com/en/tutorials/security/content-security-policy/
 */
class ContentSecurityPolicy extends BaseConfig
{
    // -------------------------------------------------------------------------
    // Broadbrush CSP management
    // -------------------------------------------------------------------------

    /**
     * Default CSP report context
     */
    public bool $reportOnly = false;

    /**
     * Specifies a URL where a browser will send reports
     * when a content security policy is violated.
     */
    public ?string $reportURI = null;

    /**
     * Specifies a reporting endpoint to which violation reports ought to be sent.
     */
    public ?string $reportTo = null;

    /**
     * Instructs user agents to rewrite URL schemes, changing
     * HTTP to HTTPS. This directive is for websites with
     * large numbers of old URLs that need to be rewritten.
     */
    public bool $upgradeInsecureRequests = false;

    // -------------------------------------------------------------------------
    // CSP DIRECTIVES SETTINGS
    // NOTE: once you set a policy to 'none', it cannot be further restricted
    // -------------------------------------------------------------------------

    /**
     * Will default to `'self'` if not overridden
     *
     * @var list<string>|string|null
     */
    public $defaultSrc;

    /**
     * Lists allowed scripts' URLs.
     *
     * @var list<string>|string
     */
    public $scriptSrc = 'self';

    /**
     * Specifies valid sources for JavaScript <script> elements.
     *
     * @var list<string>|string
     */
    public array|string $scriptSrcElem = 'self';

    /**
     * Specifies valid sources for JavaScript inline event
     * handlers and JavaScript URLs.
     *
     * @var list<string>|string
     */
    public array|string $scriptSrcAttr = 'self';

    /**
     * Lists allowed stylesheets' URLs.
     *
     * @var list<string>|string
     */
    public $styleSrc = ['self', 'unsafe-inline'];

    /**
     * Specifies valid sources for stylesheets <link> elements.
     *
     * `'unsafe-inline'` is required: block partials (hero_slider, hero_banner,
     * timeline, gallery_item, pricing_plan, footer, ...) each ship their own
     * `<style>` block by design — a block-local/portable styling pattern used
     * throughout app/Views/blocks. Nonces don't fit here: blocks are rendered
     * independently and cached, with no single per-request nonce to thread
     * through them.
     *
     * @var list<string>|string
     */
    public array|string $styleSrcElem = ['self', 'unsafe-inline'];

    /**
     * Specifies valid sources for stylesheets inline
     * style attributes and `<style>` elements.
     *
     * `'unsafe-inline'` is required: many blocks render CMS-editor-controlled
     * values (overlay/text colors, computed heights, animation delays) as
     * inline `style="..."` attributes — values only known at render time, so
     * they can't be pre-compiled into static CSS classes. Nonces don't cover
     * style attributes at all (only `<style>`/`<script>` elements).
     *
     * @var list<string>|string
     */
    public array|string $styleSrcAttr = ['self', 'unsafe-inline'];

    /**
     * Defines the origins from which images can be loaded.
     *
     * @var list<string>|string
     */
    public array|string $imageSrc = ['self', 'http:', 'https:', 'data:'];

    /**
     * Restricts the URLs that can appear in a page's `<base>` element.
     *
     * Will default to self if not overridden
     *
     * @var list<string>|string|null
     */
    public $baseURI;

    /**
     * Lists the URLs for workers and embedded frame contents
     *
     * @var list<string>|string
     */
    public $childSrc = 'self';

    /**
     * Limits the origins that you can connect to (via XHR,
     * WebSockets, and EventSource).
     *
     * @var list<string>|string
     */
    public $connectSrc = 'self';

    /**
     * Specifies the origins that can serve web fonts.
     *
     * @var list<string>|string
     */
    public $fontSrc;

    /**
     * Lists valid endpoints for submission from `<form>` tags.
     *
     * @var list<string>|string
     */
    public $formAction = 'self';

    /**
     * Specifies the sources that can embed the current page.
     * This directive applies to `<frame>`, `<iframe>`, `<embed>`,
     * and `<applet>` tags. This directive can't be used in
     * `<meta>` tags and applies only to non-HTML resources.
     *
     * @var list<string>|string|null
     */
    public $frameAncestors;

    /**
     * The frame-src directive restricts the URLs which may
     * be loaded into nested browsing contexts.
     *
     * @var list<string>|string|null
     */
    public array|string $frameSrc = ['self', 'http:', 'https:'];

    /**
     * Restricts the origins allowed to deliver video and audio.
     *
     * @var list<string>|string|null
     */
    public array|string $mediaSrc = ['self', 'http:', 'https:'];

    /**
     * Allows control over Flash and other plugins.
     *
     * @var list<string>|string
     */
    public array|string $objectSrc = ['self', 'http:', 'https:'];

    /**
     * @var list<string>|string|null
     */
    public $manifestSrc;

    /**
     * @var list<string>|string
     */
    public array|string $workerSrc = [];

    /**
     * Limits the kinds of plugins a page may invoke.
     *
     * @var list<string>|string|null
     */
    public $pluginTypes;

    /**
     * List of actions allowed.
     *
     * @var list<string>|string|null
     */
    public $sandbox;

    /**
     * Nonce placeholder for style tags.
     */
    public string $styleNonceTag = '{csp-style-nonce}';

    /**
     * Nonce placeholder for script tags.
     */
    public string $scriptNonceTag = '{csp-script-nonce}';

    /**
     * Replace nonce tag automatically?
     */
    public bool $autoNonce = true;

    public function __construct()
    {
        parent::__construct();

        $this->imageSrc = $this->directiveFromEnv('CSP_IMAGE_SRC', ['self', 'http:', 'https:', 'data:']);
        $this->frameSrc = $this->directiveFromEnv('CSP_FRAME_SRC', ['self', 'http:', 'https:']);
        $this->mediaSrc = $this->directiveFromEnv('CSP_MEDIA_SRC', ['self', 'http:', 'https:']);
        $this->objectSrc = $this->directiveFromEnv('CSP_OBJECT_SRC', ['self', 'http:', 'https:']);
    }

    /**
     * @param list<string> $defaultSources
     * @return list<string>|string
     */
    private function directiveFromEnv(string $envKey, array $defaultSources): array|string
    {
        $raw = env($envKey);
        $sources = [];

        if (is_string($raw) && trim($raw) !== '') {
            $sources = preg_split('/[\s,]+/', trim($raw)) ?: [];
        }

        if ($sources === []) {
            $sources = $defaultSources;
        }

        $sources = array_values(array_filter(array_map([$this, 'normalizeSourceToken'], $sources), static fn (string $value): bool => $value !== ''));

        return count($sources) === 1 ? $sources[0] : $sources;
    }

    private function normalizeSourceToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        return match (strtolower($token)) {
            'self', 'none', 'unsafe-inline', 'unsafe-eval', 'strict-dynamic', 'report-sample' => "'{$token}'",
            default => $token,
        };
    }
}
