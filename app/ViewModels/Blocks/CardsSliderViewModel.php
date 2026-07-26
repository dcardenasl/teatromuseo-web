<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class CardsSliderViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $cards        = $this->cards();
        $layout       = $this->configString('layout', 'slider');
        $visibleCount = min(3, max(1, $this->configInt('visible_count', 1)));

        return [
            'cards'            => $cards,
            'sectionTitle'     => $this->dataString('section_title'),
            'sectionSubtitle'  => $this->dataString('section_subtitle'),
            'isSlider'         => $layout === 'slider',
            'autoplay'         => $this->configBool('autoplay', true),
            'interval'         => max(1000, $this->configInt('interval', 5000)),
            'visibleCount'     => $visibleCount,
            'cardVariant'      => $this->configString('card_variant', 'editorial'),
            'cssClass'         => trim($this->configString('css_class')),
            'slideBasis'       => 100 / $visibleCount,
            'dotCount'         => max(1, count($cards) - $visibleCount + 1),
            'sliderWidthClass' => $visibleCount === 1 ? 'max-w-4xl' : 'max-w-6xl',
        ];
    }

    /**
     * @return list<array{eyebrow: string, title: string, body: string, meta_title: string, meta_description: string, image: array{source_kind: string, file_id: int|null, url: string}, rating: int, link_url: string, link_label: string}>
     */
    public function cards(): array
    {
        $cards = [];

        foreach ($this->children() as $child) {
            if (($child['block_key'] ?? '') !== 'slide_card') {
                continue;
            }

            $childData = is_array($child['block_data'] ?? null) ? $child['block_data'] : [];
            $childConfig = is_array($child['block_config'] ?? null) ? $child['block_config'] : [];
            $string    = static fn (string $key): string => is_scalar($childData[$key] ?? null) ? (string) $childData[$key] : '';

            $cards[] = [
                'eyebrow'          => $string('eyebrow'),
                'title'            => $string('title'),
                'body'             => $string('body'),
                'meta_title'       => $string('meta_title'),
                'meta_description' => $string('meta_description'),
                'image'            => $this->mediaReferenceFromPayload($childConfig, 'image'),
                'rating'           => is_numeric($childData['rating'] ?? null) ? (int) $childData['rating'] : 0,
                'link_url'         => lang_url($string('link_url'), $this->lang),
                'link_label'       => $string('link_label'),
            ];
        }

        return $cards;
    }
}
