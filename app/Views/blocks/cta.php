<section class="section <?= esc($cssClass) ?>">
    <div class="container-base">
        <div class="surface-default px-6 sm:px-10 lg:px-12 py-8 sm:py-10">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div class="max-w-3xl">
                    <?php if (!empty($heading)): ?>
                        <h2 class="section-title text-2xl sm:text-3xl">
                            <?= esc($heading) ?>
                        </h2>
                    <?php endif; ?>

                    <?php if (!empty($text)): ?>
                        <p class="section-copy mt-3 max-w-2xl text-base">
                            <?= esc($text) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($label) && !empty($url)): ?>
                    <div>
                        <a href="<?= esc($url) ?>"
                           class="btn btn-primary rounded-xl px-6 py-3 text-sm font-semibold">
                            <?= esc($label) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
