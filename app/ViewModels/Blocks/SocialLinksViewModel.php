<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class SocialLinksViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $heading  = $this->dataString('heading');
        $cssClass = $this->configString('css_class');

        return [
            'heading'  => $heading,
            'cssClass' => trim($cssClass),
        ];
    }
}
