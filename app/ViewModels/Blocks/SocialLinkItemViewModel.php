<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class SocialLinkItemViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $network     = $this->configString('network', 'facebook');
        $url         = $this->configString('url');
        $handle      = $this->dataString('handle');
        $customLabel = $this->dataString('custom_label');
        $customColor = $this->dataString('custom_color');
        $customSvg   = $this->dataString('custom_svg');

        // Brand mappings
        $label = 'Social';
        $color = 'bg-primary';
        $svg   = '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />';

        switch ($network) {
            case 'facebook':
                $label = 'Facebook';
                $color = 'bg-[#1877F2]';
                $svg   = '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>';
                break;
            case 'instagram':
                $label = 'Instagram';
                $color = 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400';
                $svg   = '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>';
                break;
            case 'twitter':
                $label = 'Twitter / X';
                $color = 'bg-gray-900';
                $svg   = '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>';
                break;
            case 'youtube':
                $label = 'YouTube';
                $color = 'bg-[#FF0000]';
                $svg   = '<path d="m22 8-6 4 6 4V8z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>';
                break;
            case 'linkedin':
                $label = 'LinkedIn';
                $color = 'bg-[#0A66C2]';
                $svg   = '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/>';
                break;
            case 'tiktok':
                $label = 'TikTok';
                $color = 'bg-black';
                $svg   = '<path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>';
                break;
            case 'pinterest':
                $label = 'Pinterest';
                $color = 'bg-[#E60023]';
                $svg   = '<path d="M8 22c-.6 0-1.1-.3-1.3-.8L5 16.5c-.3-.9-.6-2.4-.6-3.8C4.4 7.2 8.7 3 14 3c4.9 0 9 3.7 9 8.7 0 4.6-3 8.3-7.5 8.3-1.6 0-3-.7-3.8-1.6 0 0-.8 3.1-.9 3.5-.4 1.4-1.2 2.8-1.3 3.1-.2.4-.6.8-1 .8z"/>';
                break;
            case 'whatsapp':
                $label = 'WhatsApp';
                $color = 'bg-[#25D366]';
                $svg   = '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.746.953 3.71 1.458 5.704 1.459h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>';
                break;
            case 'github':
                $label = 'GitHub';
                $color = 'bg-[#181717]';
                $svg   = '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/>';
                break;
            case 'custom':
                $label = $customLabel !== '' ? $customLabel : 'Enlace';
                $color = $customColor !== '' ? $customColor : 'bg-primary';
                $svg   = $customSvg !== '' ? $customSvg : '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />';
                break;
        }

        return [
            'url'    => lang_url($url, $this->lang),
            'label'  => $label,
            'handle' => $handle,
            'color'  => $color,
            'svg'    => $svg,
        ];
    }
}
