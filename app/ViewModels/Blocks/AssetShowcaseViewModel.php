<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class AssetShowcaseViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $logos     = $this->logos();
        $layout    = $this->configString('layout', 'marquee');
        $speed     = $this->configString('speed', 'normal');
        $grayscale = $this->configBool('grayscale', true);

        $isMarquee = $layout === 'marquee';

        $duration = '25s';
        if ($speed === 'slow') {
            $duration = '40s';
        } elseif ($speed === 'fast') {
            $duration = '12s';
        }

        $logoStyleClass = $grayscale
            ? 'filter grayscale hover:grayscale-0 opacity-60 hover:opacity-100 transition-all duration-300'
            : 'opacity-80 hover:opacity-100 transition-all duration-300';

        return [
            'logos'          => $logos,
            'layout'         => $layout,
            'speed'          => $speed,
            'grayscale'      => $grayscale,
            'isMarquee'      => $isMarquee,
            'duration'       => $duration,
            'logoStyleClass' => $logoStyleClass,
            'cssClass'       => trim($this->configString('css_class')),
        ];
    }

    /**
     * @return list<array{logo: array{source_kind: string, file_id: int|null, url: string}, name: string, link_url: string}>
     */
    public function logos(): array
    {
        $logos = [];
        foreach ($this->children() as $child) {
            if (($child['block_key'] ?? '') !== 'asset_item') {
                continue;
            }
            $childData = is_array($child['block_data'] ?? null) ? $child['block_data'] : [];
            $childConfig = is_array($child['block_config'] ?? null) ? $child['block_config'] : [];
            $string    = static fn (string $key): string => is_scalar($childData[$key] ?? null) ? (string) $childData[$key] : '';

            $logos[] = [
                'logo'     => $this->normalizeMediaReference($childConfig['logo'] ?? []),
                'name'     => $string('name'),
                'link_url' => lang_url($string('link_url'), $this->lang),
            ];
        }

        return $logos;
    }
}
