<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class EventController extends BasePublicWebController
{
    public function index(): ResponseInterface
    {
        return $this->deliverPublicRoute(\App\Support\PublicPaths::eventsSegment($this->request->getLocale()));
    }

    public function show(string $slug): ResponseInterface
    {
        return $this->deliverPublicRoute(
            \App\Support\PublicPaths::eventsSegment($this->request->getLocale()) . '/' . trim($slug, '/'),
        );
    }
}
