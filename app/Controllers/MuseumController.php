<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class MuseumController extends BasePublicWebController
{
    public function index(): ResponseInterface
    {
        return $this->deliverPublicRoute(\App\Support\PublicPaths::catalogSegment($this->request->getLocale()));
    }

    public function show(string $idOrCode): ResponseInterface
    {
        return $this->deliverPublicRoute(
            \App\Support\PublicPaths::catalogSegment($this->request->getLocale()) . '/' . trim($idOrCode, '/'),
        );
    }
}
