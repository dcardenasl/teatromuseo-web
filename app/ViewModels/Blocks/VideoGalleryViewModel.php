<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class VideoGalleryViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $rawVideos = $this->data()['videos'] ?? [];
        $rawVideos = is_array($rawVideos) ? $rawVideos : [];

        $videos = [];
        foreach ($rawVideos as $v) {
            $video = is_array($v) ? $v : [];
            $videoUrl = is_scalar($video['video_url'] ?? null) ? (string) $video['video_url'] : '';
            $title    = is_scalar($video['title'] ?? null) ? (string) $video['title'] : '';
            $desc     = is_scalar($video['description'] ?? null) ? (string) $video['description'] : '';
            $poster   = $this->mediaReferenceFromPayload($video, 'poster');

            $embedUrl = VideoPlayerViewModel::embedUrl($videoUrl, false, false);

            $videos[] = [
                'videoUrl'    => $videoUrl,
                'title'       => $title,
                'description' => $desc,
                'poster'      => $poster,
                'embedUrl'    => $embedUrl,
                'isIframe'    => $embedUrl !== '',
            ];
        }

        return [
            'title'     => $this->dataString('title'),
            'subtitle'  => $this->dataString('subtitle'),
            'videos'    => $videos,
            'columns'   => $this->configString('columns', '3'),
            'cssClass'  => trim($this->configString('css_class')),
        ];
    }
}
