<?php
/**
 * video_player block — all variables prepared by VideoPlayerViewModel
 * (registered in BlockRenderer::VIEW_MODELS).
 *
 * @var string $videoUrl
 * @var array{source_kind: string, file_id: int|null, url: string} $poster
 * @var string $heading
 * @var bool   $autoplay
 * @var bool   $mute
 * @var bool   $loop
 * @var string $cssClass
 * @var string $embedUrl
 * @var bool   $isIframe
 * @var string $aspectRatioClass
 * @var string $uniqueId
 */

if ($videoUrl === '') {
    return;
}
?>

<section class="section-sm <?= esc($cssClass) ?>">
    <div class="max-w-5xl mx-auto">
        <?php if ($heading !== ''): ?>
            <h3 class="text-xl md:text-2xl font-bold text-slate-800 mb-4 tracking-tight text-center">
                <?= esc($heading) ?>
            </h3>
        <?php endif; ?>

        <div 
            id="<?= $uniqueId ?>"
            class="relative overflow-hidden rounded-3xl bg-slate-900 shadow-md group/video <?= $aspectRatioClass ?>"
            data-video-player
            data-embed-url="<?= esc($embedUrl) ?>"
            data-is-iframe="<?= $isIframe ? '1' : '0' ?>"
            data-native-url="<?= esc($videoUrl) ?>"
            data-autoplay="<?= $autoplay ? '1' : '0' ?>"
            data-mute="<?= $mute ? '1' : '0' ?>"
            data-loop="<?= $loop ? '1' : '0' ?>"
        >
            <?php if (($poster['url'] ?? '') !== ''): ?>
                <!-- Lazy Load Poster View -->
                <div class="absolute inset-0 z-10 cursor-pointer flex items-center justify-center transition-all duration-300" data-poster-overlay>
                    <?= view('components/responsive-image', [
                        'src'      => $poster['url'],
                        'alt'      => $heading !== '' ? $heading : 'Video Poster',
                        'class'    => 'absolute inset-0 w-full h-full object-cover group-hover/video:scale-[1.01] transition-transform duration-500',
                        'variants' => $poster['variants'] ?? null,
                        'preferredVariant' => 'lg',
                        'sizes'    => '(max-width: 1023px) calc(100vw - 2rem), 1024px',
                    ], ['saveData' => false]) ?>
                    <!-- Overlay Dark Mask -->
                    <div class="absolute inset-0 bg-slate-950/40 group-hover/video:bg-slate-950/30 transition-colors duration-300"></div>
                    
                    <!-- Pulsing Play Button -->
                    <button 
                        class="relative z-20 flex items-center justify-center w-16 h-16 md:w-20 md:h-20 rounded-full bg-white text-violet-600 shadow-lg transition-all duration-300 group-hover/video:scale-110 group-hover/video:bg-violet-600 group-hover/video:text-white"
                        aria-label="Reproducir video"
                        data-play-button
                    >
                        <!-- Pulse Ring Effect -->
                        <span class="absolute inset-0 rounded-full bg-white/30 animate-ping group-hover/video:bg-violet-600/30"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play ml-1"><polygon points="6 3 20 12 6 21 6 3"/></svg>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Fallback Content placeholder when poster is not present (renders iframe instantly) -->
            <?php if (($poster['url'] ?? '') === ''): ?>
                <?php if ($isIframe): ?>
                    <iframe 
                        src="<?= esc(str_replace('autoplay=1', 'autoplay=' . ($autoplay ? '1' : '0'), $embedUrl)) ?>"
                        class="w-full h-full border-0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen
                        title="<?= esc($heading !== '' ? $heading : 'Video Player') ?>"
                    ></iframe>
                <?php else: ?>
                    <video 
                        src="<?= esc($videoUrl) ?>"
                        class="w-full h-full object-contain"
                        controls
                        <?= $autoplay ? 'autoplay' : '' ?>
                        <?= $mute ? 'muted' : '' ?>
                        <?= $loop ? 'loop' : '' ?>
                    ></video>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php // Click-to-play behavior lives in src/js/components/videoPlayer.js (data-video-player). ?>
