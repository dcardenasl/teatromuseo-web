<?php
/** @var array<string, mixed> $data */
$references = is_array($data['entries'] ?? null) ? $data['entries'] : [];
$references = array_values(array_filter($references, static fn (mixed $item): bool => is_array($item) && is_array($item['entry'] ?? null)));
if ($references !== []): ?>
    <section class="my-8" aria-labelledby="related-entries-title-<?= esc((string) ($block['id'] ?? uniqid())) ?>">
        <h2 id="related-entries-title-<?= esc((string) ($block['id'] ?? uniqid())) ?>" class="mb-4 text-xl font-semibold text-slate-900">
            <?= esc(lang('Site.related_content')) ?>
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($references as $reference):
                $entry = $reference['entry'];
                $url = trim((string) ($entry['url'] ?? ''));
                ?>
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="font-semibold text-slate-900">
                        <?php if ($url !== ''): ?><a href="<?= esc(lang_url($url)) ?>" class="hover:text-primary hover:underline"><?php endif; ?>
                            <?= esc((string) ($entry['title'] ?? '')) ?>
                        <?php if ($url !== ''): ?></a><?php endif; ?>
                    </h3>
                    <?php if (! empty($entry['excerpt'])): ?><p class="mt-2 text-sm text-slate-600"><?= esc((string) $entry['excerpt']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
