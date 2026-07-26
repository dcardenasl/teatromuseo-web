<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class HealthController extends BaseController
{
    public function index(): ResponseInterface
    {
        $writable = is_writable(WRITEPATH);

        $state = $writable ? 'up' : 'degraded';

        return $this->response
            ->setStatusCode($writable ? 200 : 503)
            ->setJSON([
                'state'  => $state,
                'checks' => [
                    'writable' => $writable,
                ],
            ]);
    }
}
