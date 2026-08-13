<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\BlockRenderer;
use CodeIgniter\HTTP\ResponseInterface;

class BlockPreviewController extends BasePublicWebController
{
    /**
     * Render a single block type dynamically using the actual frontend BlockRenderer.
     *
     * Server-to-server only: called by the Admin panel's own
     * BlockPreviewController, which is itself gated by
     * `permission:cms.pages.read` (2026-08-12 audit finding — this endpoint
     * had no authentication of its own, only throttling). Protection is
     * opt-in via BLOCK_PREVIEW_KEY, matching CacheController's shared-secret
     * pattern: when unset, the endpoint keeps its previous throttle-only
     * behavior (no regression for deployments that haven't configured the
     * key yet); when set, a missing/incorrect X-Block-Preview-Key is
     * rejected.
     */
    public function preview(): ResponseInterface
    {
        if (! $this->hasValidPreviewKeyOrNoneConfigured()) {
            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['ok' => false, 'message' => 'Unauthorized.']);
        }

        $blockKeyRaw = $this->request->getPost('block_key');
        $configRaw   = $this->request->getPost('block_config');
        $dataRaw     = $this->request->getPost('block_data');
        $previewModeRaw = $this->request->getPost('preview_mode');
        $blockKey    = is_scalar($blockKeyRaw) ? (string) $blockKeyRaw : '';
        $configRaw   = is_scalar($configRaw) ? (string) $configRaw : '';
        $dataRaw     = is_scalar($dataRaw) ? (string) $dataRaw : '';
        $previewMode = is_scalar($previewModeRaw) ? strtolower(trim((string) $previewModeRaw)) : 'sample';

        $config = json_decode($configRaw ?: '{}', true) ?? [];
        $data   = json_decode($dataRaw ?: '{}', true) ?? [];

        if (! in_array($previewMode, ['sample', 'live'], true)) {
            $previewMode = 'sample';
        }

        $children = $previewMode === 'sample'
            ? $this->getMockChildren($blockKey)
            : [];

        if ($previewMode === 'sample') {
            // Populate placeholders when the caller explicitly wants a sample
            // preview or when no real block payload is available.
            $data = $this->getMockData($blockKey, $data, $config);
        }

        // Preserve the canonical media_reference payload used by live image blocks.
        $data = $this->applyMediaReferencePreview($blockKey, $data, $config);

        $block = [
            'block_key'    => $blockKey,
            'block_config' => $config,
            'block_data'   => $data,
            'children'     => $children,
        ];

        $lang = service('request')->getLocale();

        $blockRenderer = new BlockRenderer();
        $html = $blockRenderer->render([$block], $lang);

        return $this->response
            ->setContentType('application/json')
            ->setJSON(['html' => $html]);
    }

    /**
     * Get mock placeholder data for simple blocks when they have empty fields.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function getMockData(string $blockKey, array $data, array &$config = []): array
    {
        $sample = cms_block_preview_sample($blockKey);
        if ($sample !== []) {
            $data = array_replace($sample, $data);
        }

        if ($blockKey === 'hero_banner') {
            if (! is_array($config['image'] ?? null) || empty($config['image']['url'])) {
                $config['image'] = $this->mediaReference('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80');
            }
        }

        if ($blockKey === 'cta') {
            if (empty($data['url'])) {
                $data['url'] = '#';
            }
        }

        if ($blockKey === 'metrics_grid') {
            // Sample text comes from the template catalog.
        }

        if ($blockKey === 'social_links') {
            // Sample text comes from the template catalog.
        }

        if ($blockKey === 'alert') {
            // Sample text comes from the template catalog.
        }

        if ($blockKey === 'video_player') {
            if (empty($data['video_url'])) {
                $data['video_url'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
            }
            if (! is_array($config['poster'] ?? null) || empty($config['poster']['url'])) {
                $config['poster'] = $this->mediaReference('https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1200&q=80');
            }
        }

        if ($blockKey === 'map_embed') {
            if (empty($config['embed_url'])) {
                $config['embed_url'] = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3329.076891048822!2d-71.6186981!3d-33.0427771!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9689e13b0a7dbf2d%3A0x600fb16cd72eb0f1!2sValparaiso%2C%20Chile!5e0!3m2!1sen!2scl!4v1680000000000!5m2!1sen!2scl';
            }
        }

        if ($blockKey === 'document_download') {
            if (! is_array($config['document'] ?? null) || empty($config['document']['url'])) {
                $config['document'] = $this->mediaReference('http://localhost:8184/uploads/reporte_anual_2025.pdf');
            }
        }

        if ($blockKey === 'pdf_viewer') {
            if (! is_array($config['pdf_file'] ?? null) || empty($config['pdf_file']['url'])) {
                $config['pdf_file'] = $this->mediaReference(site_url('assets/docs/policies-handbook-demo.pdf'));
            }
        }

        if ($blockKey === 'rich_text') {
            // Sample text comes from the template catalog.
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function applyMediaReferencePreview(string $blockKey, array $data, array $config): array
    {
        if ($blockKey !== 'image') {
            return $data;
        }

        if (is_array($config['image'] ?? null)) {
            $data['image'] = $config['image'];
        }

        return $data;
    }

    /**
     * Get mock children for container blocks when they have none during preview.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getMockChildren(string $blockKey): array
    {
        if ($blockKey === '') {
            return [];
        }

        if ($blockKey === 'accordion') {
            $item = cms_block_preview_sample('accordion_item');
            return [
                [
                    'block_key' => 'accordion_item',
                    'block_config' => ['is_open' => true],
                    'block_data' => $item + ['title' => '¿Cómo funciona la vista previa?', 'content' => '<p>La vista previa renderiza el componente real usando el motor de plantillas público.</p>']
                ],
                [
                    'block_key' => 'accordion_item',
                    'block_config' => ['is_open' => false],
                    'block_data' => array_replace($item, ['title' => '¿Es fiel al diseño final?', 'content' => '<p>Sí, utiliza los mismos estilos CSS de Tailwind y tipografías que el sitio web público.</p>'])
                ]
            ];
        }

        if ($blockKey === 'gallery') {
            $item = cms_block_preview_sample('gallery_item');
            return [
                [
                    'block_key' => 'gallery_item',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80'),
                    ],
                    'block_data' => $item + ['alt' => 'Playa paradisíaca'],
                ],
                [
                    'block_key' => 'gallery_item',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=600&q=80'),
                    ],
                    'block_data' => $item + ['alt' => 'Montañas brumosas'],
                ],
                [
                    'block_key' => 'gallery_item',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1447752875215-b2761acb3c5d?auto=format&fit=crop&w=600&q=80'),
                    ],
                    'block_data' => $item + ['alt' => 'Sendero forestal'],
                ]
            ];
        }

        if ($blockKey === 'tabs') {
            $item = cms_block_preview_sample('tab_item');
            return [
                [
                    'block_key' => 'tab_item',
                    'block_config' => [],
                    'block_data' => array_replace($item, ['title' => 'Pestaña de Ejemplo 1', 'content' => '<p>Este es el contenido de la primera pestaña de ejemplo.</p>'])
                ],
                [
                    'block_key' => 'tab_item',
                    'block_config' => [],
                    'block_data' => array_replace($item, ['title' => 'Pestaña de Ejemplo 2', 'content' => '<p>Este es el contenido de la segunda pestaña de ejemplo.</p>'])
                ]
            ];
        }

        if ($blockKey === 'hero_slider') {
            $slide = cms_block_preview_sample('slide_banner');
            return [
                [
                    'block_key' => 'slide_banner',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80'),
                    ],
                    'block_data' => $slide,
                ],
                [
                    'block_key' => 'slide_banner',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=1200&q=80'),
                    ],
                    'block_data' => $slide,
                ]
            ];
        }

        if ($blockKey === 'cards_slider') {
            $card = cms_block_preview_sample('slide_card');
            return [
                [
                    'block_key' => 'slide_card',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=400&q=80'),
                    ],
                    'block_data' => $card,
                ],
                [
                    'block_key' => 'slide_card',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=400&q=80'),
                    ],
                    'block_data' => $card,
                ]
            ];
        }

        if ($blockKey === 'cards_grid') {
            $card = cms_block_preview_sample('card_item');
            return [
                [
                    'block_key' => 'card_item',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=400&q=80'),
                    ],
                    'block_data' => $card,
                ],
                [
                    'block_key' => 'card_item',
                    'block_config' => [
                        'image' => $this->mediaReference('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=400&q=80'),
                    ],
                    'block_data' => $card,
                ]
            ];
        }

        if ($blockKey === 'metrics_grid') {
            $metric = cms_block_preview_sample('metric_item');
            return [
                [
                    'block_key' => 'metric_item',
                    'block_config' => [],
                    'block_data' => $metric
                ],
                [
                    'block_key' => 'metric_item',
                    'block_config' => [],
                    'block_data' => $metric
                ]
            ];
        }

        if ($blockKey === 'asset_showcase') {
            $asset = cms_block_preview_sample('asset_item');
            return [
                [
                    'block_key' => 'asset_item',
                    'block_config' => [],
                    'block_data' => $asset + ['category' => 'document']
                ]
            ];
        }

        if ($blockKey === 'social_links') {
            $social = cms_block_preview_sample('social_link_item');
            return [
                [
                    'block_key' => 'social_link_item',
                    'block_config' => [
                        'network' => 'facebook',
                        'url' => 'https://facebook.com/example',
                    ],
                    'block_data' => $social + ['handle' => 'facebook_handle'],
                ],
                [
                    'block_key' => 'social_link_item',
                    'block_config' => [
                        'network' => 'instagram',
                        'url' => 'https://instagram.com/example',
                    ],
                    'block_data' => $social + ['handle' => '@instagram_handle'],
                ],
                [
                    'block_key' => 'social_link_item',
                    'block_config' => [
                        'network' => 'twitter',
                        'url' => 'https://twitter.com/example',
                    ],
                    'block_data' => $social + ['handle' => '@twitter_handle'],
                ],
                [
                    'block_key' => 'social_link_item',
                    'block_config' => [
                        'network' => 'youtube',
                        'url' => 'https://youtube.com/example',
                    ],
                    'block_data' => $social + ['handle' => 'youtube_handle'],
                ]
            ];
        }

        return [];
    }

    /**
     * @return array{source_kind: string, file_id: int|null, url: string}
     */
    private function mediaReference(string $url): array
    {
        return [
            'source_kind' => 'external_url',
            'file_id' => null,
            'url' => $url,
        ];
    }

    private function hasValidPreviewKeyOrNoneConfigured(): bool
    {
        $expectedKey = (string) env('BLOCK_PREVIEW_KEY', '');
        if ($expectedKey === '') {
            return true;
        }

        $receivedKey = $this->request->getHeaderLine('X-Block-Preview-Key');

        return hash_equals($expectedKey, $receivedKey);
    }
}
