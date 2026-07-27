<?php
/** @var array<string, mixed> $data */
$references = is_array($data['entries'] ?? null) ? $data['entries'] : [];
if ($references !== []): ?>
    <ul class="flex flex-wrap gap-x-5 gap-y-2" aria-label="<?= esc(lang('Site.related_content')) ?>">
        <?php foreach ($references as $reference): ?>
            <?php if (is_array($reference)): ?>
                <li><?= view('blocks/_entry_reference', ['reference' => $reference], ['saveData' => false]) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
