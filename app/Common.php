<?php

declare(strict_types=1);

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application.
 */

if (! function_exists('lang_url')) {
    /**
     * Generate localized base URL.
     */
    function lang_url(?string $url = '', ?string $locale = null): string
    {
        if (empty($url) || $url === '#') {
            return '#';
        }

        // Return absolute URLs as is
        if (preg_match('/^(https?:)?\/\//', $url)) {
            return $url;
        }

        $currentLocale = $locale !== null && $locale !== ''
            ? $locale
            : service('request')->getLocale();
        $url = '/' . ltrim($url, '/');

        // Check if URL already has a valid locale prefix
        foreach (config('App')->supportedLocales as $locale) {
            if (strpos($url, '/' . $locale . '/') === 0 || $url === '/' . $locale) {
                return base_url($url);
            }
        }

        return base_url('/' . $currentLocale . $url);
    }
}

if (! function_exists('current_lang_url')) {
    /**
     * Get the current URL in a different locale.
     *
     * @param array<string, string>|null $localizedUrls The controller-computed
     *      per-locale URLs for the current page/entry (translated collection
     *      prefix + slug), keyed by locale. Pass the view's own `$localized_urls`
     *      explicitly — this can no longer be read back from `service('renderer')`
     *      because every `view()` call in the render chain uses `saveData: false`
     *      (required to stop page data leaking into sibling partials), which also
     *      means the renderer never persists this data for a later lookup.
     */
    function current_lang_url(string $locale, ?array $localizedUrls = null): string
    {
        if (is_array($localizedUrls) && isset($localizedUrls[$locale])) {
            $uri = service('request')->getUri();
            $query = $uri->getQuery();
            return $localizedUrls[$locale] . ($query !== '' ? '?' . $query : '');
        }

        $uri = service('request')->getUri();
        $segments = $uri->getSegments();
        $supportedLocales = config('App')->supportedLocales;

        if (!empty($segments) && in_array($segments[0], $supportedLocales, true)) {
            $currentPath = implode('/', array_slice($segments, 1));
            if ($currentPath === '' || \App\Support\PublicPaths::isHomepageSlug($currentPath)) {
                $query = $uri->getQuery();

                return base_url(
                    '/' . $locale . \App\Support\PublicPaths::homepagePath($locale)
                    . ($query !== '' ? '?' . $query : ''),
                );
            }

            $segments[0] = $locale;
        } else {
            array_unshift($segments, $locale);
        }

        $path = implode('/', $segments);
        $query = $uri->getQuery();

        return base_url('/' . $path . ($query !== '' ? '?' . $query : ''));
    }
}

if (! function_exists('collection_resolve_text')) {
    /**
     * Resolve a collection display string from a prioritized list of fields.
     *
     * @param array<string, mixed> $collection
     * @param list<string> $fields
     */
    function collection_resolve_text(array $collection, array $fields, bool $humanizeFallback = false): string
    {
        foreach ($fields as $field) {
            $value = trim((string) ($collection[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        if (! $humanizeFallback) {
            return '';
        }

        foreach (['slug', 'collection_key'] as $field) {
            $value = trim((string) ($collection[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $value = preg_replace('/[-_]+/', ' ', $value) ?? $value;

            if (function_exists('mb_convert_case')) {
                return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
            }

            return ucwords($value);
        }

        return '';
    }
}

if (! function_exists('collection_display_title')) {
    /**
     * Resolve the public title for a collection without hardcoded section names.
     *
     * @param array<string, mixed> $collection
     */
    function collection_display_title(array $collection): string
    {
        return collection_resolve_text($collection, ['listing_title', 'name'], true);
    }
}

if (! function_exists('collection_display_intro')) {
    /**
     * Resolve the public intro for a collection.
     *
     * @param array<string, mixed> $collection
     */
    function collection_display_intro(array $collection): string
    {
        return collection_resolve_text($collection, ['listing_intro', 'description'], false);
    }
}

if (! function_exists('collection_url_path')) {
    /**
     * Resolve the canonical public path for a collection payload.
     *
     * @param array<string, mixed> $collection
     */
    function collection_url_path(array $collection): string
    {
        $locale = service('request')->getLocale();

        return localized_collection_url_path($collection, $locale);
    }
}

if (! function_exists('collection_url_path_info')) {
    /**
     * Match a request path against a collection's known public prefixes.
     *
     * @param array<string, mixed> $collection
     * @return array{prefix: string, remainder: string}|null
     */
    function collection_url_path_info(array $collection, string $path): ?array
    {
        $normalizedPath = trim($path, '/');
        $prefix = trim(collection_url_path($collection), '/');
        if ($prefix === '') {
            return null;
        }

        if ($normalizedPath === $prefix) {
            return [
                'prefix' => '/' . $prefix,
                'remainder' => '',
            ];
        }

        if (str_starts_with($normalizedPath, $prefix . '/')) {
            return [
                'prefix' => '/' . $prefix,
                'remainder' => substr($normalizedPath, strlen($prefix) + 1),
            ];
        }

        return null;
    }
}

if (! function_exists('localized_collection_url_path')) {
    /**
     * Resolve the canonical public path for a collection in a given locale.
     *
     * Prefers the collection's dedicated index page slug when one is
     * published. Falls back to `/{collection_key}` when there is no index
     * page — `collection_key` is a required, URL-safe field (see
     * `CollectionModel::$validationRules`), so this always yields a stable
     * path that `PageController::resolve()`'s Step 1 (collection prefix
     * match) can route back to, regardless of which page(s) happen to embed
     * a collection_listing/collection_grid block for this collection. Do not
     * fall back to the *current request path* instead — that makes entry
     * URLs depend on which page rendered them, producing different links for
     * the same entry when two pages embed the same collection.
     *
     * @param array<string, mixed> $collection
     */
    function localized_collection_url_path(array $collection, string $locale): string
    {
        $indexPage = $collection['index_page'] ?? null;
        if (is_array($indexPage)) {
            $localizedSlugs = $indexPage['localized_slugs'] ?? [];
            if (is_array($localizedSlugs) && isset($localizedSlugs[$locale])) {
                $slug = trim((string) $localizedSlugs[$locale], '/');
                if ($slug !== '') {
                    return '/' . $slug;
                }
            }
        }

        $collectionKey = trim((string) ($collection['collection_key'] ?? ''), '/');

        return $collectionKey !== '' ? '/' . $collectionKey : '';
    }
}

if (! function_exists('localized_collection_urls')) {
    /**
     * Build language-specific URLs for a collection index page.
     *
     * @param array<string, mixed> $collection
     * @return array<string, string>
     */
    function localized_collection_urls(array $collection): array
    {
        $urls = [];
        foreach (config('App')->supportedLocales as $locale) {
            $path = localized_collection_url_path($collection, $locale);
            if ($path !== '') {
                $urls[$locale] = site_url('/' . $locale . $path);
            }
        }

        return $urls;
    }
}

if (! function_exists('localized_entry_urls')) {
    /**
     * Build language-specific URLs for an entry detail page.
     *
     * @param array<string, mixed> $collection
     * @param array<string, mixed> $entry
     * @return array<string, string>
     */
    function localized_entry_urls(array $collection, array $entry): array
    {
        $urls = [];
        $localizedSlugs = is_array($entry['localized_slugs'] ?? null) ? $entry['localized_slugs'] : [];

        foreach (config('App')->supportedLocales as $locale) {
            $collectionPath = localized_collection_url_path($collection, $locale);
            if ($collectionPath === '') {
                continue;
            }
            $slug = isset($localizedSlugs[$locale]) ? trim((string) $localizedSlugs[$locale], '/') : '';
            if ($slug !== '') {
                $urls[$locale] = site_url('/' . $locale . $collectionPath . '/' . $slug);
            } else {
                // No translation for this locale — fall back to the collection index
                $urls[$locale] = site_url('/' . $locale . $collectionPath);
            }
        }

        return $urls;
    }
}

if (! function_exists('localized_date_intl_locale')) {
    /**
     * Maps a page language code to the ICU locale used for date formatting.
     * Shared by `format_localized_date()` below and
     * `App\ViewModels\Blocks\Concerns\FormatsLocalizedDateTime` so both stay
     * in sync from a single source.
     */
    function localized_date_intl_locale(string $lang): string
    {
        return match ($lang) {
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'pt' => 'pt_PT',
            default => 'es_ES',
        };
    }
}

if (! function_exists('format_localized_date')) {
    /**
     * Locale-aware, date-only display string (day + month name + year, no time
     * component) for entry cards and listings. PHP's `date()` always renders
     * English month names regardless of the page language — this formats
     * through `IntlDateFormatter` so "04 Aug 2026" reads as "4 ago 2026" on a
     * Spanish page, "4 août 2026" on a French one, etc.
     */
    function format_localized_date(string $value, string $lang): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                localized_date_intl_locale($lang),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE,
            );
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date('d-m-Y', $timestamp);
    }
}

if (! function_exists('block_text_content')) {
    /**
     * Resolve rich text content from the canonical block field.
     *
     * @param array<string, mixed> $data
     */
    function block_text_content(array $data, string $default = ''): string
    {
        $value = $data['content'] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return \App\Libraries\HtmlSanitizer::clean($value);
        }

        return $default;
    }
}

if (! function_exists('cms_block_preview_samples')) {
    /**
     * Curated preview samples for block rendering (BlockPreviewController's
     * "sample" mode). Keep these aligned with the visible preview UX rather
     * than with any one controller.
     *
     * @return array<string, array<string, mixed>>
     */
    function cms_block_preview_samples(): array
    {
        return [
            'hero_banner' => [
                'alt' => 'Imagen de fondo del hero',
                'heading' => 'Previsualización de Banner',
                'subheading' => 'Este banner utiliza las tipografías y el diseño completo de tu sitio público.',
                'cta_label' => 'Acción Principal',
                'cta_url' => '#',
            ],
            'rich_text' => [
                'content' => '<h2>Título de ejemplo</h2><p>Este es un bloque de texto enriquecido de ejemplo. Puedes incluir <strong>negritas</strong>, <em>cursivas</em>, listas y más.</p><ul><li>Elemento uno</li><li>Elemento dos</li></ul>',
            ],
            'cta' => [
                'heading' => '¿Listo para comenzar?',
                'text' => 'Únete a miles de clientes satisfechos y empieza hoy mismo.',
                'label' => 'Comenzar ahora',
                'url' => '#',
            ],
            'slide_banner' => [
                'heading' => 'Temporada 2026',
                'subtitle' => 'Programación destacada y actividades especiales.',
                'cta_label' => 'Ver programación',
                'cta_url' => '/featured',
            ],
            'accordion_item' => [
                'title' => '¿Cómo funciona la vista previa?',
                'content' => '<p>La vista previa renderiza el componente real usando el motor de plantillas público.</p>',
            ],
            'gallery_item' => [
                'alt' => 'Imagen de ejemplo',
                'caption' => 'Imagen destacada',
                'link_url' => '#',
                'link_label' => 'Ver imagen',
            ],
            'tab_item' => [
                'title' => 'Pestaña de Ejemplo 1',
                'content' => '<p>Este es el contenido de la primera pestaña de ejemplo.</p>',
            ],
            'card_item' => [
                'title' => 'Tarjeta de ejemplo',
                'description' => 'Descripción breve de la tarjeta.',
                'link_url' => '#',
                'link_label' => 'Ver más',
            ],
            'slide_card' => [
                'eyebrow' => 'Caso destacado',
                'title' => 'Tarjeta Deslizable 1',
                'body' => 'Descripción breve para la tarjeta de ejemplo en el slider.',
                'meta_title' => 'Equipo editorial',
                'meta_description' => 'Contenido CMS',
                'rating' => '0',
                'link_url' => '#',
                'link_label' => 'Ver más',
            ],
            'metric_item' => [
                'prefix' => '',
                'number' => '120',
                'suffix' => '+',
                'label' => 'Proyectos Completados',
                'description' => 'Proyectos gestionados desde el CMS.',
                'source_label' => 'Registro institucional',
                'source_url' => '',
                'icon' => 'sparkles',
            ],
            'asset_item' => [
                'name' => 'Caso de Éxito PDF',
                'link_url' => '#',
            ],
            'social_link_item' => [
                'handle' => '@example',
            ],
            'image' => [
                'alt' => 'Imagen de ejemplo',
                'caption' => 'Pie de foto de ejemplo',
            ],
            'collection_grid' => [
                'section_title' => 'Contenido destacado',
                'section_subtitle' => 'Últimas publicaciones de la colección seleccionada.',
                'view_all_label' => 'Ver todo',
                'empty_message' => 'No hay contenido publicado por el momento.',
            ],
            'collection_listing' => [
                'intro_title' => 'Listado completo',
                'intro_text' => '<p>Usa este bloque para mostrar el índice público de una colección.</p>',
                'empty_message' => 'No hay contenido disponible.',
            ],
            'pricing_plan' => [
                'name' => 'Plan Básico',
                'price' => '$29',
                'period' => '/ mes',
                'description' => 'Ideal para comenzar.',
                'features' => '<ul><li>1 proyecto</li><li>Soporte por correo</li></ul>',
                'cta_label' => 'Comenzar',
                'cta_url' => '#',
            ],
            'video_player' => [
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'heading' => 'Video de Presentación de Ejemplo',
            ],
            'compania_ficha' => [
                'name' => 'Compañía de ejemplo',
                'summary' => 'Colectivo artístico de referencia.',
            ],
            'persona_ficha' => [
                'name' => 'Persona de ejemplo',
                'role' => 'Dirección artística',
            ],
            'obra_ficha' => [
                'subtitle' => 'Pieza escénica de ejemplo',
                'synopsis' => '<p>Sinopsis breve de la obra.</p>',
                'duration' => '90 min',
            ],
            'video_ficha' => [
                'provider' => 'youtube',
                'video_id' => 'example',
            ],
            'festival_ficha' => [
                'edition' => 'Edición de ejemplo',
                'venue' => 'Teatro Museo',
                'status' => 'upcoming',
            ],
            'exposicion_ficha' => [
                'venue' => 'Sala de exposiciones',
                'description' => '<p>Descripción breve de la exposición.</p>',
            ],
            'teatroescuela_ficha' => [
                'modality' => 'presencial',
                'schedule' => 'Sábados, 10:00–13:00',
                'venue' => 'Teatro Museo',
                'capacity' => 20,
            ],
            'publicacion_metadata' => [
                'publication_type' => 'editorial',
                'publisher' => 'TeatroMuseo',
            ],
            'related_entries' => [
                'relation_type' => 'related',
            ],
            'alert' => [
                'title' => 'Aviso Importante',
                'message' => '<p>Este es un mensaje de alerta de ejemplo para mostrar cómo se ve el diseño en tu sitio público.</p>',
            ],
            'page_header' => [
                'heading' => 'Contact Us',
                'subheading' => 'We\'d love to hear from you',
                'breadcrumb_label' => 'Home',
            ],
            'contact_info' => [
                'section_title' => 'Contacto',
                'section_description' => 'Canales oficiales para escribirnos o visitarnos.',
                'address_label' => 'Address',
                'address' => '123 Main Street, Your City, Country',
                'phone_label' => 'Phone',
                'phone' => '+1 (555) 000-0000',
                'email_label' => 'Email',
                'email' => 'hola@example.com',
                'hours_label' => 'Office Hours',
                'hours' => "Monday to Friday: 9:00 - 18:00\nSaturday: 10:00 - 14:00",
            ],
            'map_embed' => [
                'title' => 'Nuestra Ubicación',
                'caption' => 'Valparaíso, Chile',
            ],
            'social_links' => [
                'heading' => 'Síguenos',
            ],
            'metrics_grid' => [
                'section_title' => 'Métricas destacadas',
                'section_subtitle' => 'Indicadores clave del proyecto.',
            ],
            'cards_slider' => [
                'section_title' => 'Historias destacadas',
                'section_subtitle' => 'Tarjetas configurables para distintos usos.',
            ],
        ];
    }
}

if (! function_exists('cms_block_preview_sample')) {
    /**
     * @return array<string, mixed>
     */
    function cms_block_preview_sample(string $blockKey): array
    {
        return cms_block_preview_samples()[$blockKey] ?? [];
    }
}
