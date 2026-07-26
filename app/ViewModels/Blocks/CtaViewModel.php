<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class CtaViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        return [
            'heading'  => $this->dataString('heading'),
            'text'     => $this->dataString('text'),
            'label'    => $this->dataString('label'),
            'url'      => lang_url($this->dataString('url', '#'), $this->lang),
            'cssClass' => trim($this->configString('css_class')),
        ];
    }
}
