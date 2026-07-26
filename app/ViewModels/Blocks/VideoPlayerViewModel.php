<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class VideoPlayerViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $videoUrl = $this->dataString('video_url');
        $heading  = $this->dataString('heading');
        $mute     = $this->configBool('mute', false);
        $loop     = $this->configBool('loop', false);
        $poster   = $this->configMediaReference('poster');

        $embedUrl = self::embedUrl($videoUrl, $mute, $loop);

        return [
            'videoUrl'         => $videoUrl,
            'poster'           => $poster,
            'heading'          => $heading,
            'autoplay'         => $this->configBool('autoplay', false),
            'mute'             => $mute,
            'loop'             => $loop,
            'cssClass'         => trim($this->configString('css_class')),
            'embedUrl'         => $embedUrl,
            'isIframe'         => $embedUrl !== '',
            'aspectRatioClass' => self::aspectRatioClass($this->configString('aspect_ratio', '16/9')),
            'uniqueId'         => 'video_' . uniqid(),
        ];
    }

    public static function getYouTubeId(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i';
        if (preg_match($pattern, $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public static function getVimeoId(string $url): ?string
    {
        $pattern = '/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?)/i';
        if (preg_match($pattern, $url, $matches) === 1) {
            return $matches[3];
        }

        return null;
    }

    /**
     * Provider embed URL for YouTube/Vimeo links, or '' for native video files.
     */
    public static function embedUrl(string $videoUrl, bool $mute, bool $loop): string
    {
        $ytId = self::getYouTubeId($videoUrl);
        if ($ytId !== null) {
            $embedUrl = "https://www.youtube-nocookie.com/embed/{$ytId}?autoplay=1";
            if ($mute) {
                $embedUrl .= '&mute=1';
            }
            if ($loop) {
                $embedUrl .= "&loop=1&playlist={$ytId}";
            }

            return $embedUrl;
        }

        $vimeoId = self::getVimeoId($videoUrl);
        if ($vimeoId !== null) {
            $embedUrl = "https://player.vimeo.com/video/{$vimeoId}?autoplay=1";
            if ($mute) {
                $embedUrl .= '&muted=1';
            }
            if ($loop) {
                $embedUrl .= '&loop=1';
            }

            return $embedUrl;
        }

        return '';
    }

    public static function aspectRatioClass(string $aspectRatio): string
    {
        return match ($aspectRatio) {
            '4/3'   => 'aspect-[4/3]',
            'auto'  => 'aspect-auto',
            default => 'aspect-video',
        };
    }
}
