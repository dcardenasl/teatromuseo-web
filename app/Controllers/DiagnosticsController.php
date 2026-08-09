<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/** Protected diagnostics endpoint for the public-read capacity audit. */
final class DiagnosticsController extends BaseController
{
    public function publicRead(): ResponseInterface
    {
        $expectedKey = (string) env('PUBLIC_READ_DIAGNOSTICS_KEY', '');

        if ($expectedKey === '') {
            return $this->response->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
        }

        $receivedKey = $this->request->getHeaderLine('X-Diagnostics-Key');
        if (! hash_equals($expectedKey, $receivedKey)) {
            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['ok' => false, 'message' => 'Unauthorized.']);
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->setJSON(Services::publicReadDiagnostics()->report());
    }
}
