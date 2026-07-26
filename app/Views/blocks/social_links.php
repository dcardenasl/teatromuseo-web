<?php
/** @var string $heading */
/** @var string $renderedChildren */
/** @var string $cssClass */
?>
<section class="section-sm <?= esc($cssClass) ?>">
    <div class="container-base">
        <div class="space-y-5">
            <?php if ($heading): ?>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    <?= esc($heading) ?>
                </h2>
            <?php endif; ?>

            <div class="flex flex-wrap gap-3">
                <?= $renderedChildren ?>
            </div>
        </div>
    </div>
</section>
