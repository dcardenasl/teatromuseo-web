<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WebApiClientInterface;

class SiteFormService extends BaseSiteService
{
    private const CACHE_TTL = 300;

    /**
     * `$apiClient` (the BFF) still serves form *definitions* — the BFF has a
     * direct-DB public-read seam for those. Submissions need the Domain's
     * own validation, CAPTCHA verification, and email-job dispatch, none of
     * which the BFF has a route for — `public/submissions` is one of the
     * `webappkey`-gated `/api/v1/public/*` domain endpoints the Web app
     * calls directly (see teatromuseo-web/CLAUDE.md and AnalyticsQueue's
     * identical direct-to-Domain pattern for tracking writes).
     */
    public function __construct(
        WebApiClientInterface $apiClient,
        private readonly WebApiClientInterface $domainClient,
    ) {
        parent::__construct($apiClient);
    }

    /**
     * Fetch the public form definition for the given language and form key.
     *
     * @return array<string, mixed>|null
     */
    public function getDefinition(string $lang, string $formKey): ?array
    {
        return $this->fetchData("public/{$lang}/forms/{$formKey}", [], self::CACHE_TTL, 'forms');
    }

    /**
     * Submit a form to the domain API.
     *
     * @param array<string, mixed> $formData     Sanitised field values keyed by field_key
     * @param string|null          $captchaToken reCAPTCHA response token (when has_captcha=true)
     *
     * @return array{ok: bool, id: int|null, messages: list<string>}
     */
    public function submit(
        string $formKey,
        array $formData,
        ?string $captchaToken = null
    ): array {
        $payload = [
            'form_key'  => $formKey,
            'form_data' => $formData,
        ];

        if ($captchaToken !== null) {
            $payload['captcha_token'] = $captchaToken;
        }

        $response = $this->domainClient->post('public/submissions', $payload);

        if (! $response['ok']) {
            return [
                'ok'       => false,
                'id'       => null,
                'messages' => $response['messages'] !== [] ? $response['messages'] : ['Error al enviar el formulario.'],
            ];
        }

        $id = is_array($response['data']) && isset($response['data']['id'])
            ? (int) $response['data']['id']
            : null;

        return ['ok' => true, 'id' => $id, 'messages' => []];
    }
}
