<?php
$content = block_text_content($data, '');
$cssClass = esc(trim($config['css_class'] ?? ''));
?>
<section class="section <?= $cssClass ?>">
    <div class="max-w-4xl mx-auto px-4">
        <div class="prose max-w-3xl mx-auto">
            <?php if (($context['is_about_page'] ?? false) === true): ?>
                <h2 class="section-title text-2xl sm:text-3xl mb-6">Sobre Nosotros</h2>
            <?php endif; ?>
            <?= $content ?>
        </div>
    </div>
</section>
