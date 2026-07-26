<?php
$content = block_text_content($data, '');
$cssClass = esc(trim($config['css_class'] ?? ''));
?>
<section class="section <?= $cssClass ?>">
    <div class="max-w-4xl mx-auto px-4">
        <div class="prose max-w-3xl mx-auto">
            <?= $content ?>
        </div>
    </div>
</section>
