<?php
/** @var array<string, mixed> $data */
/** @var string $renderedChildren */
$title = trim((string) ($data['name'] ?? $data['title'] ?? ''));
$richTextRaw = trim((string) ($data['description'] ?? $data['bio'] ?? $data['synopsis'] ?? ''));
$richText = $richTextRaw !== '' ? \App\Libraries\HtmlSanitizer::clean($richTextRaw) : '';
$referenceFields = [];
$scalarFields = [];
foreach ($data as $key => $value) {
    if (in_array($key, ['name', 'title', 'description', 'bio', 'synopsis', 'entry'], true)) {
        continue;
    }
    if ($key === 'entries' && is_array($value)) {
        $referenceFields[$key] = $value;
        continue;
    }
    if (is_array($value) && isset($value['entry_id'])) {
        $referenceFields[$key] = [$value];
        continue;
    }
    if (is_array($value) && $value !== [] && isset($value[0]['entry_id'])) {
        $referenceFields[$key] = $value;
        continue;
    }
    if (is_scalar($value) && trim((string) $value) !== '') {
        $scalarFields[$key] = (string) $value;
    }
}
?>
<article class="my-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <?php if ($title !== ''): ?><h2 class="text-2xl font-semibold text-slate-900"><?= esc($title) ?></h2><?php endif; ?>
    <?php if ($richText !== ''): ?><div class="prose prose-slate mt-4 max-w-none"><?= $richText ?></div><?php endif; ?>
    <?php if ($scalarFields !== []): ?>
        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
            <?php foreach ($scalarFields as $key => $value): ?>
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= esc(ucwords(str_replace('_', ' ', $key))) ?></dt>
                    <dd class="mt-1 text-sm text-slate-800"><?= esc($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
    <?php if ($referenceFields !== []): ?>
        <div class="mt-5 space-y-3">
            <?php foreach ($referenceFields as $key => $references):
                $references = array_values(array_filter((array) $references, static fn (mixed $item): bool => is_array($item) && is_array($item['entry'] ?? null)));
                if ($references === []) continue;
                ?>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= esc(ucwords(str_replace('_', ' ', $key))) ?></h3>
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                        <?php foreach ($references as $reference): ?>
                            <?= view('blocks/_entry_reference', ['reference' => $reference], ['saveData' => false]) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?= $renderedChildren ?? '' ?>
</article>
