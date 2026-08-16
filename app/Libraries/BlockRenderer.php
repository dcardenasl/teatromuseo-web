<?php

declare(strict_types=1);

namespace App\Libraries;

class BlockRenderer
{
    /**
     * Blocks whose view data is prepared by a dedicated view model.
     * Blocks not listed here keep receiving the raw payload directly.
     *
     * @var array<string, class-string<\App\ViewModels\Blocks\AbstractBlockViewModel>>
     */
    private const VIEW_MODELS = [
        'hero_slider'        => \App\ViewModels\Blocks\HeroSliderViewModel::class,
        'cards_slider'       => \App\ViewModels\Blocks\CardsSliderViewModel::class,
        'form_embed'         => \App\ViewModels\Blocks\FormEmbedViewModel::class,
        'video_player'       => \App\ViewModels\Blocks\VideoPlayerViewModel::class,
        'collection_grid'    => \App\ViewModels\Blocks\CollectionGridViewModel::class,
        'collection_listing' => \App\ViewModels\Blocks\CollectionListingViewModel::class,
        'collection_timeline' => \App\ViewModels\Blocks\CollectionTimelineViewModel::class,
        'metrics_grid'       => \App\ViewModels\Blocks\MetricsGridViewModel::class,
        'cta'                => \App\ViewModels\Blocks\CtaViewModel::class,
        'hero_banner'        => \App\ViewModels\Blocks\HeroBannerViewModel::class,
        'asset_showcase'     => \App\ViewModels\Blocks\AssetShowcaseViewModel::class,
        'social_links'       => \App\ViewModels\Blocks\SocialLinksViewModel::class,
        'social_link_item'   => \App\ViewModels\Blocks\SocialLinkItemViewModel::class,
        'document_download'  => \App\ViewModels\Blocks\DocumentDownloadViewModel::class,
        'video_gallery'      => \App\ViewModels\Blocks\VideoGalleryViewModel::class,
        'document_gallery'   => \App\ViewModels\Blocks\DocumentGalleryViewModel::class,
        'pdf_viewer'         => \App\ViewModels\Blocks\PdfViewerViewModel::class,
        'accordion'          => \App\ViewModels\Blocks\AccordionViewModel::class,
        'team_grid'          => \App\ViewModels\Blocks\TeamGridViewModel::class,
        'team_member'        => \App\ViewModels\Blocks\TeamMemberViewModel::class,
        'event_item_header'    => \App\ViewModels\Blocks\EventItemHeaderViewModel::class,
        'event_item_details'   => \App\ViewModels\Blocks\EventItemDetailsViewModel::class,
        'event_item_content'   => \App\ViewModels\Blocks\EventItemContentViewModel::class,
        'event_item_gallery'   => \App\ViewModels\Blocks\EventItemGalleryViewModel::class,
        'catalog_item_header'  => \App\ViewModels\Blocks\CatalogItemHeaderViewModel::class,
        'catalog_item_details' => \App\ViewModels\Blocks\CatalogItemDetailsViewModel::class,
        'catalog_item_content' => \App\ViewModels\Blocks\CatalogItemContentViewModel::class,
        'catalog_item_gallery' => \App\ViewModels\Blocks\CatalogItemGalleryViewModel::class,
        'teatroescuela_ficha' => \App\ViewModels\Blocks\TeatroEscuelaViewModel::class,
    ];

    /** @var array<string, array<string, mixed>|null> form definitions pre-loaded per render pass */
    private array $formDefinitions = [];

    /** @var int Counter of images rendered on the current page pass */
    private int $imageCount = 0;

    /** @var array<array{src: string, srcset: string, sizes: string}> List of image preloads */
    private array $imagePreloads = [];

    /**
     * Add a URL to the head preloads list.
     */
    public function addPreload(string $src, string $srcset = '', string $sizes = ''): void
    {
        foreach ($this->imagePreloads as $preload) {
            if ($preload['src'] === $src) {
                return;
            }
        }
        $this->imagePreloads[] = [
            'src'    => $src,
            'srcset' => $srcset,
            'sizes'  => $sizes,
        ];
    }

    /**
     * Get list of collected preloads.
     *
     * @return array<array{src: string, srcset: string, sizes: string}>
     */
    public function getPreloads(): array
    {
        return $this->imagePreloads;
    }

    /**
     * Increment the global image count and return the new index.
     */
    public function incrementImageCount(): int
    {
        $this->imageCount++;
        return $this->imageCount;
    }

    /**
     * Render an array of blocks to HTML.
     *
     * @param array<array<string, mixed>> $blocks Array of block data from the API
     * @param array<string, mixed> $context Optional context data for dynamic templates
     * @return string Rendered HTML
     */
    public function render(array $blocks, string $lang = 'es', array $context = []): string
    {
        $this->imageCount = 0;
        $this->imagePreloads = [];
        if (array_key_exists('form_definitions', $context) && is_array($context['form_definitions'])) {
            $this->formDefinitions = array_filter(
                $context['form_definitions'],
                static fn (mixed $definition): bool => $definition === null || is_array($definition),
            );
        } else {
            $this->formDefinitions = [];
        }

        $html = '';
        foreach ($blocks as $index => $block) {
            $block = $this->normalizeBlockNavigation($block, $lang);
            $html .= $this->renderBlock($block, $lang, $context, (string) $index);
        }

        return $html;
    }

    /**
     * Render a single block and its children recursively.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    private function renderBlock(array $block, string $lang, array $context = [], string $blockPath = ''): string
    {
        $context = $this->injectPrefetchedItem($block, $context, $blockPath);
        $blockKey = $block['block_key'] ?? 'unknown';
        $config   = $block['block_config'] ?? [];
        $data     = $block['block_data'] ?? [];
        $children = $block['children'] ?? [];

        if ($blockKey === 'gallery_item' && ($context['collection_key'] ?? '') === 'teatroescuela') {
            $image = is_array($config['image'] ?? null) ? $config['image'] : [];
            $imageUrl = trim((string) ($image['url'] ?? ''));
            if ($imageUrl !== '' && $imageUrl === trim((string) ($context['featured_image_url'] ?? ''))) {
                return '';
            }
        }

        $renderedChildren = '';
        foreach ($children as $childIndex => $child) {
            $childPath = $blockPath === ''
                ? (string) $childIndex
                : $blockPath . '.' . $childIndex;
            $renderedChildren .= $this->renderBlock(
                $child,
                $lang,
                array_merge($context, ['is_child' => true]),
                $childPath,
            );
        }

        $formDefinition = null;
        if ($blockKey === 'form_embed') {
            $formKey = (string) ($config['form_key'] ?? 'contact');
            $formDefinition = $this->formDefinitions[$formKey] ?? null;
        }

        $blockViewName = "blocks/{$blockKey}";
        $domainFichaKeys = [
            'compania_ficha',
            'exposicion_ficha',
            'festival_ficha',
            'obra_ficha',
            'persona_ficha',
            'publicacion_metadata',
            'video_ficha',
            'curso_ficha'
        ];
        if (in_array($blockKey, $domainFichaKeys, true)) {
            $blockViewName = 'blocks/domain_ficha';
        } elseif (! view_exists($blockViewName)) {
            $blockViewName = 'blocks/unknown';
        }

        $viewData = [
            'block'            => $block,
            'config'           => $config,
            'data'             => $data,
            'renderedChildren' => $renderedChildren,
            'lang'             => $lang,
            'formDefinition'   => $formDefinition,
            'context'          => $context,
        ];

        if (is_string($blockKey) && isset(self::VIEW_MODELS[$blockKey])) {
            $viewModelClass = self::VIEW_MODELS[$blockKey];
            $viewModelContext = [
                'formDefinition' => $formDefinition,
                'blockPath' => $blockPath,
            ];
            if (in_array($blockKey, ['collection_grid', 'collection_listing', 'collection_timeline', 'team_grid'], true)) {
                // Dynamic blocks consume the BFF page envelope. The renderer
                // deliberately passes only the request; ViewModels never
                // receive domain services and therefore cannot reopen a
                // hidden remote read during rendering.
                $viewModelContext['request'] = service('request');
            }
            // Also merge the dynamic template context so view models can access it if needed
            $viewModelContext = array_merge($viewModelContext, $context);
            $viewModel = new $viewModelClass($block, $lang, $viewModelContext);
            $viewData  = array_merge($viewData, $viewModel->vars());
        } else {
            // Safety net: automatically localize any URLs in data and config if no view model exists
            $viewData['data']   = $this->localizeUrlsInArray($data);
            $viewData['config'] = $this->localizeUrlsInArray($config);
        }

        // saveData defaults to true (Config\View), which persists each view()
        // call's variables into the shared view store for the rest of the
        // request — a block field like "title" would otherwise leak into the
        // page template rendered afterwards. Disable it for isolation.
        return view($blockViewName, $viewData, ['saveData' => false]);
    }

    /**
     * Normalize known internal navigation URLs before any block view consumes
     * them. This keeps CMS-authored legacy homepage aliases out of rendered
     * breadcrumbs and CTA links without altering unknown or external URLs.
     *
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function normalizeBlockNavigation(array $block, string $lang): array
    {
        $navigation = is_array($block['navigation'] ?? null) ? $block['navigation'] : null;
        if ($navigation !== null) {
            $normalizedPath = \App\Support\PublicPaths::normalizeLocalizedPath(
                (string) ($navigation['url'] ?? ''),
                $lang,
            );
            if ($normalizedPath !== null) {
                $navigation['url'] = lang_url($normalizedPath, $lang);
                $block['navigation'] = $navigation;
            }
        }

        if (is_array($block['children'] ?? null)) {
            $children = [];
            foreach ($block['children'] as $child) {
                if (is_array($child)) {
                    $children[] = $this->normalizeBlockNavigation($child, $lang);
                }
            }
            $block['children'] = $children;
        }

        return $block;
    }

    /**
     * Attach the path-keyed result of the page prefetch context to detail blocks.
     * A missing or failed result is intentionally left empty; detail ViewModels
     * render their existing empty/preview state without issuing HTTP.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     * @param string $blockPath
     * @return array<string, mixed>
     */
    private function injectPrefetchedItem(array $block, array $context, string $blockPath): array
    {
        $blockKey = (string) ($block['block_key'] ?? '');

        $prefetched = is_array($context['block_prefetch'][$blockPath] ?? null)
            ? $context['block_prefetch'][$blockPath]
            : null;
        $item = $this->firstPrefetchedItem($prefetched);

        if ($item !== null && str_starts_with($blockKey, 'event_item_')) {
            $context['event_item'] = $item;
        }

        if ($item !== null && str_starts_with($blockKey, 'catalog_item_')) {
            $context['catalog_item'] = $item;
        }

        return $context;
    }

    /**
     * @param mixed $result
     * @return array<string, mixed>|null
     */
    private function firstPrefetchedItem(mixed $result): ?array
    {
        if (! is_array($result) || ! ($result['ok'] ?? false)) {
            return null;
        }

        $data = $result['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }

        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        return array_is_list($data)
            ? (is_array($data[0] ?? null) ? $data[0] : null)
            : $data;
    }

    /**
     * Recursively localizes any string values in an array whose keys suggest they are URLs.
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private function localizeUrlsInArray(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($this->looksLikeMediaReference($value)) {
                    continue;
                }
                $array[$key] = $this->localizeUrlsInArray($value);
            } elseif (is_string($value) && $value !== '') {
                $isUrlKey = ($key === 'url' || str_ends_with($key, '_url') || str_ends_with($key, '_path'));
                $isAsset  = (bool) preg_match('/\.(png|jpe?g|gif|svg|webp|pdf|zip|mp4|webm)$/i', $value);

                if ($isUrlKey && ! $isAsset) {
                    $array[$key] = lang_url($value);
                }
            }
        }

        return $array;
    }

    /**
     * Media reference payloads are canonical data and must not be rewritten by
     * URL localization. Their `url` value can be an external URL or a Hub
     * preview URL that should be preserved as stored.
     *
     * @param array<string, mixed> $value
     */
    private function looksLikeMediaReference(array $value): bool
    {
        return array_key_exists('source_kind', $value)
            && (array_key_exists('file_id', $value) || array_key_exists('url', $value) || array_key_exists('external_url', $value));
    }
}

/**
 * Helper function to check if a view file exists.
 */
function view_exists(string $view): bool
{
    if ($view === '') {
        return false;
    }

    $file = APPPATH . 'Views/' . str_replace('.', '/', $view) . '.php';
    if (is_file($file)) {
        return true;
    }

    $locator = \Config\Services::locator();
    return $locator->locateFile($view, 'Views') !== false;
}
