<article class="container mx-auto px-4 py-0">
    <?php if (($showPageHeading ?? true) === true): ?>
        <h1 class="text-4xl font-bold mb-4"><?= esc($title ?? '') ?></h1>

        <?php if (!empty($excerpt)): ?>
            <p class="text-lg text-gray-600 mb-8">
                <?= esc($excerpt) ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="space-y-8 sm:space-y-10">
        <?= $renderedBlocks ?? '' ?>
    </div>
</article>
