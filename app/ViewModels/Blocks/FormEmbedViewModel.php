<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class FormEmbedViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $formKey        = $this->configString('form_key', 'contact');
        $formDefinition = $this->resolveFormDefinition($formKey);

        $showInfoBoxes = ! array_key_exists('show_info_boxes', $this->config())
            || ($this->config()['show_info_boxes'] !== false && $this->config()['show_info_boxes'] !== 'false');

        return [
            'formKey'          => $formKey,
            'cssClass'         => $this->configString('css_class'),
            'showInfoBoxes'    => $showInfoBoxes,
            'heading'          => $this->dataString('heading'),
            'description'      => $this->dataString('description'),
            'infoEmailLabel'   => $this->dataString('info_email_label'),
            'infoEmailDesc'    => $this->dataString('info_email_desc'),
            'infoPhoneLabel'   => $this->dataString('info_phone_label'),
            'infoPhoneDesc'    => $this->dataString('info_phone_desc'),
            'formDefinition'   => $formDefinition,
            'fields'           => is_array($formDefinition['fields'] ?? null) ? $formDefinition['fields'] : [],
            'submitLabel'      => $this->definitionString($formDefinition, 'submit_label', 'Enviar'),
            'successMsg'       => $this->definitionString($formDefinition, 'success_message', '¡Mensaje enviado! Nos pondremos en contacto pronto.'),
            'hasCaptcha'       => ! empty($formDefinition['has_captcha']),
            'recaptchaSiteKey' => $this->recaptchaSiteKey(),
            'inputClass'       => 'form-input rounded-xl border-slate-300 bg-white px-4 py-3 text-sm shadow-none',
        ];
    }

    /**
     * Definition injected by BlockRenderer's preload pass, or lazy fallback.
     *
     * @return array<string, mixed>|null
     */
    private function resolveFormDefinition(string $formKey): ?array
    {
        if (array_key_exists('formDefinition', $this->context)) {
            return is_array($this->context['formDefinition']) ? $this->context['formDefinition'] : null;
        }

        if (is_array($this->context['form_definitions'] ?? null)
            && array_key_exists($formKey, $this->context['form_definitions'])) {
            $definition = $this->context['form_definitions'][$formKey];

            return is_array($definition) ? $definition : null;
        }

        try {
            return \Config\Services::siteFormService()->getDefinition($this->lang, $formKey);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed>|null $formDefinition
     */
    private function definitionString(?array $formDefinition, string $key, string $default): string
    {
        $value = $formDefinition[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    private function recaptchaSiteKey(): string
    {
        $settings = $this->context['settings'] ?? null;
        if (is_array($settings) && array_key_exists('recaptcha_site_key', $settings)) {
            $key = $settings['recaptcha_site_key'];

            return is_scalar($key) ? (string) $key : '';
        }

        try {
            $key = \Config\Services::siteSettingsService()
                ->get('recaptcha_site_key', env('RECAPTCHA_SITE_KEY', ''));

            return is_scalar($key) ? (string) $key : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
