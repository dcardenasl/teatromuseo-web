<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class HeroBannerViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $image = $this->configMediaReference('image');
        $imageUrl = $image['url'];

        // White is only a safe default while the dark overlay over the
        // background image is actually rendered. Without an image there is no
        // overlay, so the block sits directly on the page background — default
        // to dark text there instead of invisible white-on-white.
        $defaultTextColor = $imageUrl !== '' ? '#ffffff' : '#0f172a';

        return [
            'image'       => $image,
            'alt'         => $this->dataString('alt'),
            'heading'     => $this->dataString('heading'),
            'subheading'  => $this->dataString('subheading'),
            'cta_label'   => $this->dataString('cta_label'),
            'cta_url'       => lang_url($this->dataString('cta_url', '#'), $this->lang),
            'cssClass'      => trim($this->configString('css_class')),
            'text_color'    => trim($this->configString('text_color', $defaultTextColor)),
            'overlay_color' => trim($this->configString('overlay_color', 'rgba(15, 23, 42, 0.4)')),
        ];
    }
}
