<?php
/** @var string $url */
/** @var string $label */
/** @var string $handle */
/** @var string $color */
/** @var string $svg */

if ($url === '') {
    return;
}
?>
<a href="<?= esc($url) ?>"
   target="_blank"
   rel="noopener noreferrer"
   class="inline-flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:border-slate-300 hover:text-primary">
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full <?= esc($color) ?> text-white">
        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <?= $svg ?>
        </svg>
    </span>
    <span><?= esc($label) ?></span>
    <?php if ($handle !== ''): ?>
        <span class="text-slate-400"><?= esc($handle) ?></span>
    <?php endif; ?>
</a>
