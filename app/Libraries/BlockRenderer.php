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
        'team_member'        => \App\ViewModels\Blocks\TeamMemberViewModel::class,
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
        $this->preloadFormDefinitions($blocks, $lang);

        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block, $lang, $context);
        }

        return $html;
    }

    /**
     * Render a single block and its children recursively.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    private function renderBlock(array $block, string $lang, array $context = []): string
    {
        $blockKey = $block['block_key'] ?? 'unknown';
        $config   = $block['block_config'] ?? [];
        $data     = $block['block_data'] ?? [];
        $children = $block['children'] ?? [];

        $renderedChildren = '';
        foreach ($children as $child) {
            $renderedChildren .= $this->renderBlock($child, $lang, $context);
        }

        $formDefinition = null;
        if ($blockKey === 'form_embed') {
            $formKey = (string) ($config['form_key'] ?? 'contact');
            $formDefinition = $this->formDefinitions[$formKey] ?? null;
        }

        $blockViewName = "blocks/{$blockKey}";
        if (! view_exists($blockViewName)) {
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
            $viewModelContext = ['formDefinition' => $formDefinition];
            if ($blockKey === 'collection_grid' || $blockKey === 'collection_listing') {
                // These two view models need the current request (GET filters,
                // preview-mode detection) and the Site*Service adapters. Resolving
                // them here — the composition boundary — keeps the view models
                // themselves free of service()/Config\Services::x() calls.
                $viewModelContext += [
                    'request' => service('request'),
                    'siteCollectionService' => \Config\Services::siteCollectionService(),
                    'siteEntryService' => \Config\Services::siteEntryService(),
                    'siteCategoryService' => \Config\Services::siteCategoryService(),
                    'siteTagService' => \Config\Services::siteTagService(),
                    'siteCatalogService' => \Config\Services::siteCatalogService(),
                    'siteEventService' => \Config\Services::siteEventService(),
                ];
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
     * Pre-load form definitions for all form_embed blocks found in the block tree.
     *
     * @param array<array<string, mixed>> $blocks
     */
    private function preloadFormDefinitions(array $blocks, string $lang): void
    {
        foreach ($blocks as $block) {
            if (($block['block_key'] ?? '') === 'form_embed') {
                $formKey = (string) (($block['block_config'] ?? [])['form_key'] ?? 'contact');
                if (! array_key_exists($formKey, $this->formDefinitions)) {
                    try {
                        $this->formDefinitions[$formKey] = \Config\Services::siteFormService()
                            ->getDefinition($lang, $formKey);
                    } catch (\Throwable) {
                        $this->formDefinitions[$formKey] = null;
                    }
                }
            }
            $children = $block['children'] ?? [];
            if ($children !== []) {
                $this->preloadFormDefinitions($children, $lang);
            }
        }
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
