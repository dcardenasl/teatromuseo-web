<?php
/** @var array<string, mixed> $reference */
$entry = is_array($reference['entry'] ?? null) ? $reference['entry'] : null;
if ($entry === null) {
    return;
}
$url = trim((string) ($entry['url'] ?? ''));
$title = trim((string) ($entry['title'] ?? ''));
if ($title === '') {
    return;
}
?>
<?php if ($url !== ''): ?>
    <a href="<?= esc(lang_url($url)) ?>" class="text-primary hover:underline"><?= esc($title) ?></a>
<?php else: ?>
    <span><?= esc($title) ?></span>
<?php endif; ?>
