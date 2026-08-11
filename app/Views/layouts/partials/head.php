<?php
$siteConfig = config('App');
$supportedLocales = $siteConfig->supportedLocales ?? [];
$defaultLocale = $siteConfig->defaultLocale ?? ($supportedLocales[0] ?? service('request')->getLocale());

$resolvedTitle = (isset($pageTitle) && trim((string) $pageTitle) !== '')
    ? $pageTitle
    : ($settings['site_name'] ?? lang('Site.site_default_name'));
$resolvedDescription = (isset($metaDescription) && trim((string) $metaDescription) !== '')
    ? $metaDescription
    : ($settings['site_description'] ?? trim($resolvedTitle));

if (trim((string) $resolvedDescription) === '') {
    $resolvedDescription = $resolvedTitle;
}

$siteLogoUrl  = is_array($settings['site_logo']  ?? null) ? (string) ($settings['site_logo']['url']  ?? '') : '';
if ($siteLogoUrl === '') {
    $siteLogoUrl = (string) ($settings['site_logo_url'] ?? '');
}
$faviconUrl   = is_array($settings['favicon']     ?? null) ? (string) ($settings['favicon']['url']    ?? '') : '';
if ($faviconUrl === '') {
    $faviconUrl = (string) ($settings['favicon_url'] ?? '');
}
$resolvedOgImage = $ogImage ?? ($siteLogoUrl !== '' ? $siteLogoUrl : null);
$resolvedOgType = $ogType ?? 'website';
$resolvedCanonicalUrl = $canonicalUrl ?? site_url(service('request')->getPath());

$resolvedSchemaData = $schemaData ?? null;
if (! is_array($resolvedSchemaData) || $resolvedSchemaData === []) {
    if ($resolvedOgType === 'article') {
        $siteName = (string) ($settings['site_name'] ?? '');
        $publisher = $siteName !== '' || $siteLogoUrl !== ''
            ? array_filter([
                '@type' => 'Organization',
                'name'  => $siteName !== '' ? $siteName : null,
                'logo'  => $siteLogoUrl !== '' ? ['@type' => 'ImageObject', 'url' => $siteLogoUrl] : null,
            ], static fn ($v) => $v !== null)
            : null;

        $resolvedSchemaData = array_filter([
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => $resolvedTitle,
            'description'      => $resolvedDescription,
            'image'            => $resolvedOgImage !== null ? [$resolvedOgImage] : null,
            'datePublished'    => ! empty($articlePublishedTime) ? date('c', strtotime((string) $articlePublishedTime)) : null,
            'dateModified'     => ! empty($articleModifiedTime) ? date('c', strtotime((string) $articleModifiedTime)) : null,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $resolvedCanonicalUrl],
            'publisher'        => $publisher,
        ], static fn ($v) => $v !== null);
    } else {
        $resolvedSchemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $resolvedTitle,
            'url' => $resolvedCanonicalUrl,
            'description' => $resolvedDescription,
        ];
    }
}

$analyticsProvider = $settings['analytics_provider'] ?? 'none';
$analyticsId       = $settings['analytics_id'] ?? '';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= esc($resolvedTitle) ?></title>
<meta name="description" content="<?= esc($resolvedDescription) ?>">

<meta name="robots" content="<?= esc((isset($metaRobots) && trim((string) $metaRobots) !== '') ? $metaRobots : 'index, follow') ?>">

<?php if (! empty($canonicalUrl)): ?>
    <link rel="canonical" href="<?= esc($canonicalUrl) ?>">
<?php endif; ?>

<?php if ($faviconUrl !== ''): ?>
    <link rel="icon" href="<?= esc($faviconUrl) ?>">
<?php endif; ?>

<?php foreach ($supportedLocales as $locale): ?>
    <link rel="alternate" hreflang="<?= esc($locale) ?>" href="<?= esc(current_lang_url($locale, $localized_urls ?? null)) ?>">
<?php endforeach; ?>
<?php if (! empty($defaultLocale)): ?>
    <link rel="alternate" hreflang="x-default" href="<?= esc(current_lang_url($defaultLocale, $localized_urls ?? null)) ?>">
<?php endif; ?>

<?php if (! empty($resolvedOgImage)): ?>
    <meta property="og:image" content="<?= esc($resolvedOgImage) ?>">
<?php endif; ?>

<meta property="og:title" content="<?= esc($resolvedTitle) ?>">
<meta property="og:description" content="<?= esc($resolvedDescription) ?>">
<meta property="og:type" content="<?= esc($resolvedOgType) ?>">

<?php if ($resolvedOgType === 'article' && ! empty($articlePublishedTime)): ?>
    <meta property="article:published_time" content="<?= esc(date('c', strtotime((string) $articlePublishedTime))) ?>">
<?php endif; ?>
<?php if ($resolvedOgType === 'article' && ! empty($articleModifiedTime)): ?>
    <meta property="article:modified_time" content="<?= esc(date('c', strtotime((string) $articleModifiedTime))) ?>">
<?php endif; ?>

<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= esc($resolvedTitle) ?>">
<meta name="twitter:description" content="<?= esc($resolvedDescription) ?>">
<?php if (! empty($resolvedOgImage)): ?>
    <meta name="twitter:image" content="<?= esc($resolvedOgImage) ?>">
<?php endif; ?>

<?php if (! empty($resolvedSchemaData)): ?>
    <script type="application/ld+json">
        <?= json_encode($resolvedSchemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
<?php endif; ?>

<?php if ($analyticsProvider === 'ga4' && $analyticsId !== ''): ?>
    <script <?= csp_script_nonce() ?> async src="https://www.googletagmanager.com/gtag/js?id=<?= esc($analyticsId) ?>"></script>
    <script <?= csp_script_nonce() ?>>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= esc($analyticsId, 'js') ?>');</script>
<?php elseif ($analyticsProvider === 'plausible' && $analyticsId !== ''): ?>
    <script <?= csp_script_nonce() ?> defer data-domain="<?= esc($analyticsId) ?>" src="https://plausible.io/js/script.js"></script>
<?php elseif ($analyticsProvider === 'fathom' && $analyticsId !== ''): ?>
    <script <?= csp_script_nonce() ?> src="https://cdn.usefathom.com/script.js" data-site="<?= esc($analyticsId) ?>" defer></script>
<?php endif; ?>

<?php
$compiledCssPath = FCPATH . 'assets/css/compiled.css';
$compiledCssVersion = is_file($compiledCssPath)
    ? (string) (md5_file($compiledCssPath) ?: filemtime($compiledCssPath))
    : (string) time();
?>

<?php foreach (\Config\Services::blockRenderer()->getPreloads() as $preload): ?>
    <link rel="preload" as="image" href="<?= esc($preload['src']) ?>"
        <?php if ($preload['srcset'] !== ''): ?>
            imagesrcset="<?= esc($preload['srcset']) ?>"
            imagesizes="<?= esc($preload['sizes']) ?>"
        <?php endif; ?>
        fetchpriority="high">
<?php endforeach; ?>

<link rel="stylesheet" href="<?= base_url('assets/css/compiled.css?v=' . $compiledCssVersion) ?>">
