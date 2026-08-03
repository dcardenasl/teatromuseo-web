<?php
/** @var array<string, mixed> $config */
/** @var array<string, mixed> $data */
/** @var array<string, mixed> $context */

$sectionTitle       = $data['section_title'] ?? '';
$sectionDescription = $data['section_description'] ?? '';
$addressLabel       = $data['address_label'] ?? '';
$address            = $data['address'] ?? '';
$phoneLabel         = $data['phone_label'] ?? '';
$phone              = $data['phone'] ?? '';
$emailLabel         = $data['email_label'] ?? '';
$email              = $data['email'] ?? '';
$hoursLabel         = $data['hours_label'] ?? '';
$hours              = $data['hours'] ?? '';
$layout             = (string) ($config['layout'] ?? 'stacked');
$cssClass           = $config['css_class'] ?? '';
$isChild            = $context['is_child'] ?? false;

if ($sectionTitle === '' && $address === '' && $phone === '' && $email === '' && $hours === '') {
    return;
}

$gridClass = $layout === 'two_columns'
    ? 'grid gap-6 sm:grid-cols-2'
    : 'space-y-4';
?>

<?php if ($isChild): ?>
    <div class="surface-card p-6 sm:p-8 space-y-6 <?= esc($cssClass) ?>">
        <?php if ($sectionTitle): ?>
            <h3 class="text-xl font-bold tracking-tight text-slate-900 border-b border-slate-100 pb-3">
                <?= esc($sectionTitle) ?>
            </h3>
        <?php endif; ?>
        <?php if ($sectionDescription): ?>
            <p class="section-copy text-sm">
                <?= esc($sectionDescription) ?>
            </p>
        <?php endif; ?>

        <div class="<?= esc($gridClass) ?>">
            <?php if ($address): ?>
                <div class="border-b border-slate-100 pb-4">
                    <p class="section-eyebrow">
                        <?= esc($addressLabel) ?>
                    </p>
                    <p class="section-copy mt-2 text-sm">
                        <?= esc($address) ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($phone): ?>
                <div class="border-b border-slate-100 pb-4">
                    <p class="section-eyebrow">
                        <?= esc($phoneLabel) ?>
                    </p>
                    <a href="tel:<?= esc(preg_replace('/\s+/', '', $phone)) ?>"
                       class="section-copy mt-2 inline-flex text-sm transition-colors hover:text-primary">
                        <?= esc($phone) ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($email): ?>
                <div class="border-b border-slate-100 pb-4">
                    <p class="section-eyebrow">
                        <?= esc($emailLabel) ?>
                    </p>
                    <a href="mailto:<?= esc($email) ?>"
                       class="section-copy mt-2 inline-flex text-sm transition-colors hover:text-primary">
                        <?= esc($email) ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($hours): ?>
                <div class="pb-2">
                    <p class="section-eyebrow">
                        <?= esc($hoursLabel) ?>
                    </p>
                    <p class="section-copy mt-2 whitespace-pre-line text-sm"><?= esc($hours) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <section class="section <?= esc($cssClass) ?>">
        <div class="container-base">
            <div class="max-w-4xl space-y-6">
                <?php if ($sectionTitle): ?>
                    <h2 class="section-title text-2xl sm:text-3xl">
                        <?= esc($sectionTitle) ?>
                    </h2>
                <?php endif; ?>
                <?php if ($sectionDescription): ?>
                    <p class="section-copy max-w-xl text-base">
                        <?= esc($sectionDescription) ?>
                    </p>
                <?php endif; ?>

                <div class="<?= esc($gridClass) ?>">
                    <?php if ($address): ?>
                        <div class="border-b border-slate-200 pb-4">
                            <p class="section-eyebrow">
                                <?= esc($addressLabel) ?>
                            </p>
                            <p class="section-copy mt-2 text-sm">
                                <?= esc($address) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($phone): ?>
                        <div class="border-b border-slate-200 pb-4">
                            <p class="section-eyebrow">
                                <?= esc($phoneLabel) ?>
                            </p>
                            <a href="tel:<?= esc(preg_replace('/\s+/', '', $phone)) ?>"
                               class="section-copy mt-2 inline-flex text-sm transition-colors hover:text-primary">
                                <?= esc($phone) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($email): ?>
                        <div class="border-b border-slate-200 pb-4">
                            <p class="section-eyebrow">
                                <?= esc($emailLabel) ?>
                            </p>
                            <a href="mailto:<?= esc($email) ?>"
                               class="section-copy mt-2 inline-flex text-sm transition-colors hover:text-primary">
                                <?= esc($email) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($hours): ?>
                        <div>
                            <p class="section-eyebrow">
                                <?= esc($hoursLabel) ?>
                            </p>
                            <p class="section-copy mt-2 whitespace-pre-line text-sm"><?= esc($hours) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>
