<?php
/**
 * form_embed block — all variables prepared by FormEmbedViewModel
 * (registered in BlockRenderer::VIEW_MODELS).
 *
 * @var string                     $formKey
 * @var string                     $cssClass
 * @var bool                       $showInfoBoxes
 * @var string                     $heading
 * @var string                     $description
 * @var string                     $infoEmailLabel
 * @var string                     $infoEmailDesc
 * @var string                     $infoPhoneLabel
 * @var string                     $infoPhoneDesc
 * @var array<string, mixed>|null  $formDefinition
 * @var list<array<string, mixed>> $fields
 * @var string                     $submitLabel
 * @var string                     $successMsg
 * @var bool                       $hasCaptcha
 * @var string                     $recaptchaSiteKey
 * @var string                     $inputClass
 */

// Flash data keyed by form_key so multiple forms on the same page don't collide.
// Session state is render-time only, so it stays in the view, not the view model.
$sent   = session()->getFlashdata("form_sent_{$formKey}");
$errors = (array) (session()->getFlashdata("form_errors_{$formKey}") ?? []);

$hasLeftContent = ($heading !== '' || $description !== '' || ($showInfoBoxes && ($infoEmailLabel !== '' || $infoPhoneLabel !== '')));
$isChild = $context['is_child'] ?? false;
?>

<?php if (!$isChild): ?>
<section class="section <?= esc($cssClass) ?>">
    <div class="container-base">
<?php endif; ?>
        <?php if ($hasLeftContent): ?>
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-start">

                <?php /* ── Left column: heading + info boxes ───────────────────── */ ?>
                <div class="space-y-5">
                    <?php if ($heading !== ''): ?>
                        <h2 class="section-title text-3xl sm:text-4xl"><?= esc($heading) ?></h2>
                    <?php endif; ?>
                    <?php if ($description !== ''): ?>
                        <p class="section-copy max-w-xl text-base"><?= esc($description) ?></p>
                    <?php endif; ?>

                    <?php if ($sent): ?>
                        <div class="surface-card-compact border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            <?= esc($successMsg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($errors['_form'])): ?>
                        <div class="surface-card-compact border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            <?= esc($errors['_form']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($showInfoBoxes && ($infoEmailLabel !== '' || $infoPhoneLabel !== '')): ?>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <?php if ($infoEmailLabel !== ''): ?>
                                <div class="surface-card-compact px-4 py-4">
                                    <p class="section-title text-sm"><?= esc($infoEmailLabel) ?></p>
                                    <?php if ($infoEmailDesc !== ''): ?>
                                        <p class="section-copy mt-1 text-sm"><?= esc($infoEmailDesc) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($infoPhoneLabel !== ''): ?>
                                <div class="surface-card-compact px-4 py-4">
                                    <p class="section-title text-sm"><?= esc($infoPhoneLabel) ?></p>
                                    <?php if ($infoPhoneDesc !== ''): ?>
                                        <p class="section-copy mt-1 text-sm"><?= esc($infoPhoneDesc) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php /* ── Right column: form ── */ ?>
                <div class="pt-6 lg:pl-10 lg:pt-0">
        <?php else: ?>
            <div class="<?= $isChild ? 'surface-card p-6 sm:p-8 w-full' : 'max-w-2xl mx-auto w-full' ?>">
                <?php if ($sent): ?>
                    <div class="surface-card p-6 sm:p-8 shadow-sm text-center">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 mb-4">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-2"><?= esc($successMsg) ?></h3>
                    </div>
                <?php else: ?>
                    <?php if (isset($errors['_form'])): ?>
                        <div class="surface-card-compact border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 mb-5">
                            <?= esc($errors['_form']) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
        <?php endif; ?>

        <?php if (! $sent): ?>
            <?php if ($formDefinition === null): ?>
                <?php /* Form unavailable — degrade gracefully, no error exposed to public */ ?>
            <?php else: ?>
                <div class="surface-card p-6 sm:p-8 shadow-sm">
                    <form method="post"
                          action="<?= site_url("forms/{$formKey}/submit") ?>"
                          class="space-y-5"
                          id="form-<?= esc($formKey) ?>">
                        <?= csrf_field() ?>

                        <?php // Honeypot — hidden from real users, tempting to bots. Handled server-side in FormController. ?>
                        <div class="hidden" aria-hidden="true">
                            <label for="cf_website_<?= esc($formKey) ?>">Website</label>
                            <input type="text" name="website" id="cf_website_<?= esc($formKey) ?>" tabindex="-1" autocomplete="off" />
                        </div>

                        <?php if ($hasCaptcha && $recaptchaSiteKey !== ''): ?>
                            <input type="hidden" name="g_recaptcha_response" id="g_recaptcha_response_<?= esc($formKey) ?>" />
                        <?php endif; ?>

                        <?php foreach ($fields as $field): ?>
                            <?php
                            $fKey         = (string) ($field['field_key'] ?? '');
                            $fType        = (string) ($field['field_type'] ?? 'text');
                            $fOptions     = is_array($field['options'] ?? null) ? $field['options'] : [];
                            $label        = (string) ($field['label'] ?? $fKey);
                            $placeholder  = (string) ($field['placeholder'] ?? '');
                            $helpText     = (string) ($field['help_text'] ?? '');
                            $isRequired   = ! empty($field['is_required']);
                            $fieldError   = $errors[$fKey] ?? '';
                            $inputId      = 'cf_' . esc($formKey) . '_' . esc($fKey);
                            $oldValue     = esc((string) old($fKey, ''));
                            $errorClass   = $fieldError !== '' ? ' !border-rose-400 !ring-1 !ring-rose-400' : '';
                            ?>
                            <div>
                                <label for="<?= $inputId ?>" class="mb-2 block text-sm font-medium text-slate-700">
                                    <?= esc($label) ?>
                                    <?php if ($isRequired): ?><span class="text-rose-500"> *</span><?php endif; ?>
                                </label>

                                <?php if ($fType === 'textarea'): ?>
                                    <textarea id="<?= $inputId ?>"
                                              name="<?= esc($fKey) ?>"
                                              <?= $isRequired ? 'required' : '' ?>
                                              rows="6"
                                              placeholder="<?= esc($placeholder) ?>"
                                              class="<?= $inputClass . $errorClass ?> block min-h-[9rem] w-full resize-none"><?= $oldValue ?></textarea>
                                <?php elseif ($fType === 'select'): ?>
                                    <select id="<?= $inputId ?>"
                                            name="<?= esc($fKey) ?>"
                                            <?= $isRequired ? 'required' : '' ?>
                                            class="<?= $inputClass . $errorClass ?> w-full">
                                        <option value="" <?= $oldValue === '' ? 'selected' : '' ?>><?= esc($placeholder !== '' ? $placeholder : '—') ?></option>
                                        <?php foreach ($fOptions as $opt): ?>
                                            <?php $optValue = (string) ($opt['value'] ?? ''); ?>
                                            <option value="<?= esc($optValue) ?>" <?= $oldValue === $optValue ? 'selected' : '' ?>>
                                                <?= esc((string) ($opt['label'] ?? $optValue)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($fType === 'radio'): ?>
                                    <div id="<?= $inputId ?>" class="space-y-2">
                                        <?php foreach ($fOptions as $i => $opt): ?>
                                            <?php $optValue = (string) ($opt['value'] ?? ''); ?>
                                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                                <input type="radio"
                                                       name="<?= esc($fKey) ?>"
                                                       value="<?= esc($optValue) ?>"
                                                       <?= $oldValue === $optValue ? 'checked' : '' ?>
                                                       <?= $isRequired ? 'required' : '' ?>
                                                       class="h-4 w-4 border-slate-300 text-primary focus:ring-primary" />
                                                <?= esc((string) ($opt['label'] ?? $optValue)) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($fType === 'checkbox'): ?>
                                    <?php $oldSelected = array_map('strval', (array) old($fKey, [])); ?>
                                    <div id="<?= $inputId ?>" class="space-y-2">
                                        <?php foreach ($fOptions as $opt): ?>
                                            <?php $optValue = (string) ($opt['value'] ?? ''); ?>
                                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox"
                                                       name="<?= esc($fKey) ?>[]"
                                                       value="<?= esc($optValue) ?>"
                                                       <?= in_array($optValue, $oldSelected, true) ? 'checked' : '' ?>
                                                       class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary" />
                                                <?= esc((string) ($opt['label'] ?? $optValue)) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <input type="<?= esc($fType) ?>"
                                           id="<?= $inputId ?>"
                                           name="<?= esc($fKey) ?>"
                                           <?= $isRequired ? 'required' : '' ?>
                                           placeholder="<?= esc($placeholder) ?>"
                                           value="<?= $oldValue ?>"
                                           class="<?= $inputClass . $errorClass ?> w-full" />
                                <?php endif; ?>

                                <?php if ($fieldError !== ''): ?>
                                    <p class="mt-1 text-xs text-rose-600"><?= esc($fieldError) ?></p>
                                <?php elseif ($helpText !== ''): ?>
                                    <span class="mt-1 block text-xs text-slate-400 font-normal"><?= esc($helpText) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <button type="submit"
                                class="btn btn-primary w-full rounded-xl px-6 py-3.5 text-sm font-semibold">
                            <?= esc($submitLabel) ?>
                        </button>
                    </form>
                </div>

                <?php if ($hasCaptcha && $recaptchaSiteKey !== ''): ?>
                    <script <?= csp_script_nonce() ?>>
                    document.addEventListener('DOMContentLoaded', function () {
                        var form = document.getElementById('form-<?= esc($formKey) ?>');
                        if (!form) return;
                        form.addEventListener('submit', function (e) {
                            e.preventDefault();
                            var btn = form.querySelector('button[type=submit]');
                            if (btn) btn.disabled = true;
                            grecaptcha.ready(function () {
                                grecaptcha.execute('<?= esc($recaptchaSiteKey) ?>', { action: 'submit' })
                                    .then(function (token) {
                                        var input = document.getElementById('g_recaptcha_response_<?= esc($formKey) ?>');
                                        if (input) input.value = token;
                                        form.submit();
                                    })
                                    .catch(function () {
                                        if (btn) btn.disabled = false;
                                    });
                            });
                        });
                    });
                    </script>
                    <?php if (! defined('RECAPTCHA_SCRIPT_LOADED')): ?>
                        <?php define('RECAPTCHA_SCRIPT_LOADED', true); ?>
                        <script <?= csp_script_nonce() ?> src="https://www.google.com/recaptcha/api.js?render=<?= esc($recaptchaSiteKey) ?>" async defer></script>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($hasLeftContent): ?>
                </div> <?php /* Close pt-6 lg:pl-10 lg:pt-0 */ ?>
            </div> <?php /* Close grid ... */ ?>
        <?php else: ?>
            </div> <?php /* Close max-w-2xl or card wrapper */ ?>
        <?php endif; ?>

<?php if (!$isChild): ?>
    </div>
</section>
<?php endif; ?>
