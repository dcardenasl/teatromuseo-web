<?php
/**
 * @var array $block
 * @var string $title
 * @var string $subtitle
 * @var array $videos
 * @var string $columns
 * @var string $cssClass
 */

$colClasses = [
    '2' => 'grid-cols-1 sm:grid-cols-2',
    '3' => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
    '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
];
$colClass = $colClasses[$columns] ?? $colClasses['3'];
$galleryId = uniqid('video_gal_');
?>

<section id="timeline-item-videos" class="section-lg bg-slate-50/50 scroll-mt-16 <?= esc($cssClass) ?>" data-video-gallery-id="<?= $galleryId ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($title !== ''): ?>
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-3 bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                    <?= esc($title) ?>
                </h2>
                <?php if ($subtitle !== ''): ?>
                    <p class="text-base text-slate-500">
                        <?= esc($subtitle) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($videos === []): ?>
            <div class="rounded-3xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-400">
                <?= esc(lang('Site.video_gallery_empty')) ?>
            </div>
        <?php else: ?>
            <div class="grid gap-6 <?= $colClass ?>">
                <?php foreach ($videos as $idx => $video): 
                    $poster = ($video['poster']['url'] ?? '') !== '' ? $video['poster']['url'] : 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=600&auto=format&fit=crop&q=60';
                ?>
                    <div class="group cursor-pointer rounded-2xl border border-slate-200/60 bg-white overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 flex flex-col"
                         data-video-index="<?= $idx ?>"
                         data-video-url="<?= esc($video['videoUrl']) ?>"
                         data-embed-url="<?= esc($video['embedUrl']) ?>"
                         data-is-iframe="<?= $video['isIframe'] ? '1' : '0' ?>"
                         data-video-title="<?= esc($video['title']) ?>">
                        
                        <!-- Thumbnail Wrapper -->
                        <div class="relative aspect-video overflow-hidden bg-slate-900 shrink-0">
                            <?= view('components/responsive-image', [
                                'src'      => $poster,
                                'alt'      => $video['title'],
                                'class'    => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500',
                                'variants' => $video['poster']['variants'] ?? null,
                            ], ['saveData' => false]) ?>
                            <div class="absolute inset-0 bg-slate-950/40 group-hover:bg-slate-950/30 transition-colors duration-300 flex items-center justify-center">
                                <span class="flex items-center justify-center w-12 h-12 rounded-full bg-white text-violet-600 shadow-md group-hover:scale-110 group-hover:bg-violet-600 group-hover:text-white transition-all duration-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 ml-0.5">
                                        <path fill-rule="evenodd" d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="font-bold text-slate-800 tracking-tight group-hover:text-violet-600 transition-colors line-clamp-1 mb-1.5">
                                    <?= esc($video['title']) ?>
                                </h3>
                                <?php if ($video['description'] !== ''): ?>
                                    <p class="text-xs text-slate-500 line-clamp-2">
                                        <?= esc($video['description']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lightbox Modal -->
    <div data-video-modal
         role="dialog"
         aria-modal="true"
         aria-hidden="true"
         class="fixed inset-0 z-[100] hidden flex flex-col items-center justify-center bg-black/95 p-4 text-white select-none">
        
        <!-- Top bar -->
        <div class="w-full max-w-5xl flex items-center justify-between py-2 border-b border-white/10 mb-4">
            <h4 data-video-modal-title class="text-base font-bold truncate pr-4 text-slate-100"></h4>
            <button type="button"
                    data-video-close
                    class="rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition-colors focus:outline-none"
                    aria-label="<?= esc(lang('Site.video_modal_close')) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Video Player Body -->
        <div class="relative w-full max-w-5xl aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/5 flex items-center justify-center">
            <div data-video-container class="w-full h-full">
                <!-- Iframe or native video goes here dynamically -->
            </div>
        </div>

        <!-- Bottom Navigation Bar -->
        <div class="w-full max-w-5xl flex items-center justify-between mt-4">
            <button type="button"
                    data-video-prev
                    class="flex items-center gap-1 bg-white/10 hover:bg-white/20 px-4 py-2 rounded-xl text-sm font-semibold transition-colors disabled:opacity-30 disabled:pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                <span><?= esc(lang('Site.video_modal_previous')) ?></span>
            </button>

            <span data-video-modal-counter class="text-xs text-slate-400"></span>

            <button type="button"
                    data-video-next
                    class="flex items-center gap-1 bg-white/10 hover:bg-white/20 px-4 py-2 rounded-xl text-sm font-semibold transition-colors disabled:opacity-30 disabled:pointer-events-none">
                <span><?= esc(lang('Site.video_modal_next')) ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </div>
    </div>
</section>

<script>
(function() {
    const root = document.querySelector('[data-video-gallery-id="<?= $galleryId ?>"]');
    if (!root) return;

    const cards = root.querySelectorAll('[data-video-index]');
    const modal = root.querySelector('[data-video-modal]');
    if (!modal || cards.length === 0) return;

    const modalTitle = modal.querySelector('[data-video-modal-title]');
    const modalCounter = modal.querySelector('[data-video-modal-counter]');
    const container = modal.querySelector('[data-video-container]');
    const closeBtn = modal.querySelector('[data-video-close]');
    const prevBtn = modal.querySelector('[data-video-prev]');
    const nextBtn = modal.querySelector('[data-video-next]');
    
    let activeIndex = -1;

    const getVideoData = (index) => {
        const card = cards[index];
        if (!card) return null;
        return {
            title: card.getAttribute('data-video-title') || '',
            embedUrl: card.getAttribute('data-embed-url') || '',
            videoUrl: card.getAttribute('data-video-url') || '',
            isIframe: card.getAttribute('data-is-iframe') === '1'
        };
    };

    const renderVideo = (index) => {
        const data = getVideoData(index);
        if (!data) return;

        activeIndex = index;
        modalTitle.textContent = data.title;
        modalCounter.textContent = `${index + 1} / ${cards.length}`;

        // Clear previous player
        container.innerHTML = '';

        if (data.isIframe) {
            const iframe = document.createElement('iframe');
            iframe.src = data.embedUrl;
            iframe.className = 'w-full h-full border-0';
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
            iframe.allowFullscreen = true;
            container.appendChild(iframe);
        } else {
            const video = document.createElement('video');
            video.src = data.videoUrl;
            video.className = 'w-full h-full object-contain';
            video.controls = true;
            video.autoplay = true;
            container.appendChild(video);
        }

        // Enable/Disable buttons
        prevBtn.disabled = index === 0;
        nextBtn.disabled = index === cards.length - 1;
    };

    const openModal = (index) => {
        renderVideo(index);
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        container.innerHTML = ''; // Stop video playback
    };

    cards.forEach((card, idx) => {
        card.addEventListener('click', () => openModal(idx));
    });

    closeBtn.addEventListener('click', closeModal);
    prevBtn.addEventListener('click', () => { if (activeIndex > 0) renderVideo(activeIndex - 1); });
    nextBtn.addEventListener('click', () => { if (activeIndex < cards.length - 1) renderVideo(activeIndex + 1); });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowLeft' && activeIndex > 0) renderVideo(activeIndex - 1);
        if (e.key === 'ArrowRight' && activeIndex < cards.length - 1) renderVideo(activeIndex + 1);
    });
})();
</script>
