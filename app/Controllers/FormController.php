<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Config\Services;

/**
 * Handles public dynamic form submissions.
 *
 * Flow:
 * 1. Fetch form definition from Domain (has_captcha, required fields, field types)
 * 2. Validate required fields and email format server-side
 * 3. Strip tags from all values
 * 4. POST sanitised data and CAPTCHA token to Domain API → cms_form_submissions
 *    Domain dispatches email jobs (notification + autoreply) via Hub M2M
 * 5. Redirect back with flash message keyed by form_key
 */
class FormController extends BasePublicWebController
{
    public function submit(string $formKey): RedirectResponse
    {
        /** @var \App\Services\SiteFormService $formService */
        $formService = Services::siteFormService();

        $lang       = $this->detectLang();
        $definition = $formService->getDefinition($lang, $formKey);

        // ── 0. Honeypot: silently accept-and-drop bot submissions ─────────
        // Real users never see or fill the "website" field. Bots that fill
        // every input trip it. Return a success-looking redirect so bots get
        // no signal that they were filtered.
        if (trim($this->postString('website')) !== '') {
            log_message('info', "[FormController] Honeypot triggered for form '{$formKey}' from IP: " . $this->request->getIPAddress());

            return redirect()->back()->with("form_success_{$formKey}", true);
        }

        // ── 1. Validate required fields and types ─────────────────────────
        $fields = $definition['fields'] ?? [];
        $errors = [];

        foreach ($fields as $field) {
            $key       = $field['field_key'] ?? '';
            $fieldType = $field['field_type'] ?? 'text';
            $isChoice  = in_array($fieldType, ['select', 'radio', 'checkbox'], true);

            if ($fieldType === 'checkbox') {
                $selected = $this->postStringList($key);

                if (! empty($field['is_required']) && $selected === []) {
                    $errors[$key] = $field['error_required'] ?? 'Este campo es obligatorio.';
                    continue;
                }

                if ($selected !== [] && ! $this->valuesAllowed($selected, $field)) {
                    $errors[$key] = $field['error_invalid'] ?? 'Selección inválida.';
                }

                continue;
            }

            $value = $this->postString($key);

            if (! empty($field['is_required']) && trim($value) === '') {
                $errors[$key] = $field['error_required'] ?? 'Este campo es obligatorio.';
                continue;
            }

            if ($value === '') {
                continue;
            }

            if ($fieldType === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$key] = $field['error_invalid'] ?? 'Introduce un email válido.';
                continue;
            }

            if ($isChoice && ! $this->valuesAllowed([$value], $field)) {
                $errors[$key] = $field['error_invalid'] ?? 'Selección inválida.';
            }
        }

        if ($errors !== []) {
            return redirect()->back()
                ->withInput()
                ->with("form_errors_{$formKey}", $errors);
        }

        if (! empty($definition['has_captcha']) && trim($this->postString('g_recaptcha_response')) === '') {
            return redirect()->back()
                ->withInput()
                ->with("form_errors_{$formKey}", [
                    '_captcha' => 'No se pudo validar el CAPTCHA. Inténtelo de nuevo.',
                ]);
        }

        // ── 2. Build sanitised form data ──────────────────────────────────
        $formData = [];
        foreach ($fields as $field) {
            $key       = $field['field_key'] ?? '';
            $fieldType = $field['field_type'] ?? 'text';

            if ($fieldType === 'checkbox') {
                $formData[$key] = array_map('strip_tags', $this->postStringList($key));
                continue;
            }

            $raw = $this->postString($key);

            $formData[$key] = $fieldType === 'email'
                ? strtolower(trim($raw))
                : strip_tags($raw);
        }

        // ── 3. Submit to Domain API ───────────────────────────────────────
        $captchaToken = ! empty($definition['has_captcha'])
            ? ($this->postString('g_recaptcha_response') ?: null)
            : null;

        $result = $formService->submit($formKey, $formData, $captchaToken);

        if (! $result['ok']) {
            log_message('error', "[FormController] Domain API error for form '{$formKey}': " . implode(', ', $result['messages']));
            return redirect()->back()
                ->withInput()
                ->with("form_errors_{$formKey}", ['_form' => 'No se pudo enviar el formulario. Inténtelo más tarde.']);
        }

        return redirect()->back()->with("form_sent_{$formKey}", true);
    }

    /**
     * POST value as string; non-string payloads (arrays, nulls) become ''.
     */
    private function postString(string $key): string
    {
        $value = $this->request->getPost($key);

        return is_string($value) ? $value : '';
    }

    /**
     * POST value as a list of strings, for checkbox-group fields submitted as
     * `name="field[]"`. Non-array payloads become an empty list.
     *
     * @return list<string>
     */
    private function postStringList(string $key): array
    {
        $value = $this->request->getPost($key);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value
        ), static fn (string $item): bool => $item !== ''));
    }

    /**
     * Whether every submitted value is one of the field's configured option
     * values. Fields without an options list (or a non-choice type) pass
     * trivially — this only guards select/radio/checkbox against tampered
     * requests submitting values that were never offered in the form.
     *
     * @param list<string>          $values
     * @param array<string, mixed>  $field
     */
    private function valuesAllowed(array $values, array $field): bool
    {
        $options = $field['options'] ?? [];
        if (! is_array($options) || $options === []) {
            return true;
        }

        $allowed = array_map(
            static fn (mixed $opt): string => is_array($opt) ? (string) ($opt['value'] ?? '') : '',
            $options
        );

        foreach ($values as $value) {
            if (! in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    // ── Locale detection ──────────────────────────────────────────────────

    private function detectLang(): string
    {
        $locale = $this->request->getLocale();

        return $locale !== '' ? $locale : (string) config('App')->defaultLocale;
    }
}
