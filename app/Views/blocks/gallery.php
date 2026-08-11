<?php
/** @var array<string, mixed> $block */
/** @var array<string, mixed> $config */
/** @var array<string, mixed> $data */

$presentationMode = (string) ($config['presentation_mode'] ?? 'modal_preview');
if (! in_array($presentationMode, ['grid', 'inline_preview', 'modal_preview'], true)) {
    $presentationMode = 'modal_preview';
}

$columns = (string) ($config['columns'] ?? '3');
$gap = (string) ($config['gap'] ?? 'medium');
$cssClass = trim((string) ($config['css_class'] ?? ''));
$galleryId = uniqid('gallery_', true);

$gapClasses = [
    'none' => 'gap-0',
    'small' => 'gap-2',
    'medium' => 'gap-4 md:gap-6',
    'large' => 'gap-6 md:gap-8',
];
$gapClass = $gapClasses[$gap] ?? $gapClasses['medium'];

$colClasses = [
    '2' => 'grid-cols-1 sm:grid-cols-2',
    '3' => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
    '4' => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4',
    '6' => 'grid-cols-2 sm:grid-cols-3 md:grid-cols-6',
];
$colClass = $colClasses[$columns] ?? $colClasses['3'];
$shellClass = $presentationMode === 'inline_preview'
    ? 'max-w-7xl mx-auto my-8 px-4'
    : 'max-w-6xl mx-auto my-8 px-4';

$showInlinePreview = $presentationMode === 'inline_preview';
$showModalPreview = $presentationMode === 'modal_preview';
$title = trim((string) ($data['title'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$openImageLabel = lang('Site.gallery_open_image');
$openImageCaptionLabel = lang('Site.gallery_open_image_caption');
?>

<section
    data-gallery-root
    data-gallery-id="<?= esc($galleryId) ?>"
    data-gallery-mode="<?= esc($presentationMode) ?>"
    class="<?= esc($shellClass) ?> <?= esc($cssClass) ?>"
>
    <?php if ($title !== '' || $description !== ''): ?>
        <header class="mb-6 max-w-3xl">
            <?php if ($title !== ''): ?>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl"><?= esc($title) ?></h2>
            <?php endif; ?>
            <?php if ($description !== ''): ?>
                <p class="mt-2 text-base leading-7 text-slate-600"><?= esc($description) ?></p>
            <?php endif; ?>
        </header>
    <?php endif; ?>
    <?php if ($showInlinePreview): ?>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)]">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="aspect-[4/3] bg-slate-100">
                    <img
                        data-gallery-preview-image
                        src=""
                        alt=""
                        class="hidden h-full w-full object-cover"
                        loading="lazy"
                    >
                    <div data-gallery-preview-empty class="flex h-full items-center justify-center p-8 text-sm text-slate-500">
                        <?= esc(lang('Site.collection_empty')) ?>
                    </div>
                </div>
                <div class="border-t border-slate-200 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400"><?= esc(lang('Site.gallery_inline_preview_label')) ?></p>
                    <h3 data-gallery-preview-caption class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"></h3>
                    <p data-gallery-preview-alt class="mt-2 text-sm leading-6 text-slate-600"></p>
                    <div data-gallery-preview-counter class="mt-4 text-xs text-slate-400"></div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-slate-500"><?= esc(lang('Site.gallery_inline_preview_label')) ?></p>
                    <p class="mt-2 text-sm leading-6 text-slate-600"><?= esc(lang('Site.gallery_inline_preview_hint')) ?></p>
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <?= $renderedChildren ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="grid <?= esc($colClass) ?> <?= esc($gapClass) ?>">
            <?= $renderedChildren ?>
        </div>
    <?php endif; ?>

    <?php if ($showModalPreview): ?>
        <div
            data-gallery-modal
            role="dialog"
            aria-modal="true"
            aria-hidden="true"
            class="fixed inset-0 hidden flex-col bg-black/95 p-2 text-white select-none sm:p-4 md:p-8"
        >
            <!-- Close button (top-right) -->
            <div class="flex justify-end flex-shrink-0">
                <button
                    type="button"
                    data-gallery-close
                    class="rounded-full bg-white/10 p-2 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
                    aria-label="<?= esc(lang('Site.gallery_close_modal')) ?>"
                    title="<?= esc(lang('Site.gallery_close_modal')) ?> (Esc)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Image and navigation -->
            <div class="flex flex-1 items-center justify-between gap-2 sm:gap-3 md:gap-4 min-h-0 overflow-hidden">
                <!-- Prev button -->
                <button
                    type="button"
                    data-gallery-prev
                    class="shrink-0 rounded-full bg-white/10 p-2 sm:p-3 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50 disabled:opacity-50"
                    aria-label="<?= esc(lang('Site.gallery_previous')) ?>"
                    title="<?= esc(lang('Site.gallery_previous')) ?> (←)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Image container -->
                <div class="relative flex-1 flex items-center justify-center min-h-0 min-w-0">
                    <img
                        data-gallery-modal-image
                        src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                        alt=""
                        class="max-h-[90vh] max-w-[90vw] object-contain rounded shadow-2xl transition-all duration-300"
                        data-image-fallback="fail"
                        data-image-fallback-alt="<?= esc(lang('Site.image_failed_to_load'), 'attr') ?>"
                        decoding="async"
                    >
                </div>

                <!-- Next button -->
                <button
                    type="button"
                    data-gallery-next
                    class="shrink-0 rounded-full bg-white/10 p-2 sm:p-3 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50 disabled:opacity-50"
                    aria-label="<?= esc(lang('Site.gallery_next')) ?>"
                    title="<?= esc(lang('Site.gallery_next')) ?> (→)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <!-- Caption and metadata (bottom) -->
            <div class="flex-shrink-0 mx-auto max-w-2xl space-y-2 p-2 sm:p-4 text-center text-white/90 w-full">
                <p data-gallery-modal-caption class="text-sm sm:text-base font-medium md:text-lg truncate text-white/90"></p>
                <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3">
                    <p data-gallery-modal-counter class="text-xs text-white/50 flex-shrink-0"></p>
                    <a
                        data-gallery-modal-link
                        href="#"
                        class="hidden rounded-full bg-white/15 px-3 py-1.5 sm:px-4 sm:py-2 text-xs font-semibold text-white transition-colors hover:bg-white/25 flex-shrink-0"
                    >
                        <?= esc(lang('Site.gallery_view_page')) ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<script <?= csp_script_nonce() ?>>
(function () {
    const root = document.querySelector('[data-gallery-id="<?= esc($galleryId) ?>"]');
    if (!root || root.dataset.galleryInitialized === '1') {
        return;
    }
    root.dataset.galleryInitialized = '1';

    const mode = root.dataset.galleryMode || 'modal_preview';
    const items = Array.from(root.querySelectorAll('[data-gallery-item]'));
    if (items.length === 0) {
        return;
    }

    const isInteractiveMode = mode === 'inline_preview' || mode === 'modal_preview';
    const previewActiveClasses = ['ring-2', 'ring-sky-500', 'ring-offset-2', 'ring-offset-white'];
    const defaultModalLinkLabel = root.querySelector('[data-gallery-modal-link]')?.textContent?.trim() || '';
    let previousFocusedElement = null;
    const openImageLabel = <?= json_encode($openImageLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const openImageCaptionLabel = <?= json_encode($openImageCaptionLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const getItemData = (index) => {
        const item = items[index];
        if (!item) {
            return null;
        }

        return {
            url: item.dataset.galleryUrl || '',
            previewUrl: item.dataset.galleryPreviewUrl || item.dataset.galleryUrl || '',
            previewSrcset: item.dataset.galleryPreviewSrcset || '',
            previewSizes: item.dataset.galleryPreviewSizes || '',
            alt: item.dataset.galleryAlt || '',
            caption: item.dataset.galleryCaption || '',
            linkUrl: item.dataset.galleryLinkUrl || '',
            linkLabel: item.dataset.galleryLinkLabel || '',
        };
    };

    const bindInteractiveItem = (item, index) => {
        if (isInteractiveMode) {
            item.setAttribute('role', 'button');
            item.setAttribute('tabindex', '0');
            if (mode === 'inline_preview') {
                item.classList.add('cursor-pointer');
            } else {
                item.classList.add('cursor-zoom-in');
            }
            const data = getItemData(index);
            const ariaLabel = data && data.caption
                ? openImageCaptionLabel.replace('{caption}', data.caption)
                : openImageLabel;
            item.setAttribute('aria-label', ariaLabel);
            item.setAttribute('title', ariaLabel);
        }

        item.addEventListener('click', (event) => {
            if (event.target.closest('[data-gallery-link]')) {
                return;
            }

            const data = getItemData(index);
            if (!data) {
                return;
            }

            if (mode === 'inline_preview') {
                updateInlinePreview(index, data);
                return;
            }

            if (mode === 'modal_preview') {
                openModal(index, data);
            }
        });

        item.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();

            const data = getItemData(index);
            if (!data) {
                return;
            }

            if (mode === 'inline_preview') {
                updateInlinePreview(index, data);
                return;
            }

            if (mode === 'modal_preview') {
                openModal(index, data);
            }
        });
    };

    let previewImage = null;
    let previewEmpty = null;
    let previewCaption = null;
    let previewAlt = null;
    let previewCounter = null;

    const updateInlinePreview = (index, data) => {
        if (!previewImage || !previewEmpty || !previewCaption || !previewAlt || !previewCounter) {
            return;
        }

        root.querySelectorAll('[data-gallery-item]').forEach((item, itemIndex) => {
            previewActiveClasses.forEach((className) => item.classList.toggle(className, itemIndex === index));
        });

        if (data.previewSrcset) {
            previewImage.srcset = data.previewSrcset;
            if (data.previewSizes) {
                previewImage.sizes = data.previewSizes;
            }
        } else {
            previewImage.removeAttribute('srcset');
            previewImage.removeAttribute('sizes');
        }
        previewImage.src = data.previewUrl;
        previewImage.alt = data.alt || '';
        previewImage.classList.remove('hidden');
        previewEmpty.classList.add('hidden');
        previewCaption.textContent = data.caption || data.alt || '';
        previewAlt.textContent = data.alt || '';
        previewCounter.textContent = `${index + 1} / ${items.length}`;
    };

    items.forEach((item, index) => {
        bindInteractiveItem(item, index);
    });

    if (mode === 'inline_preview') {
        previewImage = root.querySelector('[data-gallery-preview-image]');
        previewEmpty = root.querySelector('[data-gallery-preview-empty]');
        previewCaption = root.querySelector('[data-gallery-preview-caption]');
        previewAlt = root.querySelector('[data-gallery-preview-alt]');
        previewCounter = root.querySelector('[data-gallery-preview-counter]');

        updateInlinePreview(0, getItemData(0));
        return;
    }

    if (mode !== 'modal_preview') {
        return;
    }

    const modal = root.querySelector('[data-gallery-modal]');
    if (modal) {
        document.body.appendChild(modal);
    }
    const modalImage = modal ? modal.querySelector('[data-gallery-modal-image]') : null;
    const modalCaption = modal ? modal.querySelector('[data-gallery-modal-caption]') : null;
    const modalCounter = modal ? modal.querySelector('[data-gallery-modal-counter]') : null;
    const modalLink = modal ? modal.querySelector('[data-gallery-modal-link]') : null;
    const closeButton = modal ? modal.querySelector('[data-gallery-close]') : null;
    const prevButton = modal ? modal.querySelector('[data-gallery-prev]') : null;
    const nextButton = modal ? modal.querySelector('[data-gallery-next]') : null;
    let activeIndex = 0;

    const renderModal = (index) => {
        const data = getItemData(index);
        if (!data || !modal || !modalImage || !modalCaption || !modalCounter || !modalLink) {
            return;
        }

        activeIndex = index;
        modalImage.classList.remove('opacity-50');
        modalImage.src = data.url;
        modalImage.alt = data.alt || '';
        modalCaption.textContent = data.caption || '';
        modalCounter.textContent = `${index + 1} / ${items.length}`;

        if (data.linkUrl) {
            modalLink.href = data.linkUrl;
            modalLink.textContent = data.linkLabel || defaultModalLinkLabel;
            modalLink.classList.remove('hidden');
        } else {
            modalLink.classList.add('hidden');
        }
    };

    const updateNavButtonStates = () => {
        const isFirst = activeIndex === 0;
        const isLast = activeIndex === items.length - 1;

        if (prevButton) {
            prevButton.setAttribute('aria-disabled', isFirst ? 'true' : 'false');
            prevButton.style.opacity = isFirst ? '0.5' : '1';
            prevButton.style.pointerEvents = isFirst ? 'none' : 'auto';
        }
        if (nextButton) {
            nextButton.setAttribute('aria-disabled', isLast ? 'true' : 'false');
            nextButton.style.opacity = isLast ? '0.5' : '1';
            nextButton.style.pointerEvents = isLast ? 'none' : 'auto';
        }
    };

    const focusTrap = (event) => {
        if (!modal || modal.classList.contains('hidden')) {
            return;
        }
        const focusableElements = modal.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])');
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement?.focus?.();
        } else if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement?.focus?.();
        }
    };

    const openModal = (index, data) => {
        if (!modal || !data) {
            return;
        }

        previousFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        renderModal(index);
        updateNavButtonStates();
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        // Focus trap setup
        modal.addEventListener('keydown', focusTrap);
        closeButton?.focus?.();
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }

        modal.removeEventListener('keydown', focusTrap);
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        previousFocusedElement?.focus?.();
    };

    const step = (delta) => {
        if (items.length === 0) {
            return;
        }

        const nextIndex = (activeIndex + delta + items.length) % items.length;
        renderModal(nextIndex);
        updateNavButtonStates();
    };

    closeButton?.addEventListener('click', closeModal);
    prevButton?.addEventListener('click', () => step(-1));
    nextButton?.addEventListener('click', () => step(1));

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (modal?.classList.contains('hidden')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            step(-1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            step(1);
        }
    });
})();
</script>
