<?php
/** @var array<string, mixed> $block */
/** @var array<string, mixed> $data */

// When rendered inside the parent 'tabs' block, this view is bypassed to allow Alpine.js header control.
// This is a fallback wrapper if rendered stand-alone.
$content = block_text_content($data, '');
if ($content === '') {
    return;
}
?>
<div class="prose prose-slate max-w-none text-slate-600">
    <?= $content ?>
</div>
