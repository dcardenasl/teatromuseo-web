<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

abstract class BasePublicWebController extends BaseController
{
    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = []): ResponseInterface
    {
        $data['view'] = $view;

        if (empty($data['canonicalUrl'])) {
            $data['canonicalUrl'] = site_url($this->request->getPath());
        }

        // Pre-load global layout data: menus and settings
        if (! isset($data['mainMenu'])) {
            try {
                $data['mainMenu'] = \Config\Services::siteMenuService()->getMenu('main');
            } catch (\Throwable) {
                $data['mainMenu'] = ['items' => []];
            }
        }

        if (! isset($data['footerMenu'])) {
            try {
                $data['footerMenu'] = \Config\Services::siteMenuService()->getMenu('footer');
            } catch (\Throwable) {
                $data['footerMenu'] = ['items' => []];
            }
        }

        if (! isset($data['legalMenu'])) {
            try {
                $data['legalMenu'] = \Config\Services::siteMenuService()->getMenu('legal');
            } catch (\Throwable) {
                $data['legalMenu'] = ['items' => []];
            }
        }

        if (! isset($data['settings'])) {
            try {
                $data['settings'] = \Config\Services::siteSettingsService()->getAll();
            } catch (\Throwable) {
                $data['settings'] = [];
            }
        }

        if (! array_key_exists('schemaData', $data)) {
            $data['schemaData'] = null;
        }

        // layouts/public.php forwards the full page data to nested partials
        // (head, $view) as a single $data variable, so it needs it under its
        // own 'data' key explicitly — it must not rely on Config\View's
        // saveData persistence to leak it in as a side effect.
        $data['data'] = $data;

        // saveData:false — Config\View::$saveData defaults to true and would
        // otherwise persist this render's data into the shared view store for
        // the rest of the process (e.g. across PHPUnit test cases).
        $body = view('layouts/public', $data, ['saveData' => false]);
        $etag = '"' . sha1($body) . '"';

        return $this->response
            ->setHeader('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
            ->setHeader('ETag', $etag)
            ->setHeader('Vary', 'Accept-Language')
            ->setBody($body);
    }

    protected function notFound(string $message = 'Página no encontrada'): ResponseInterface
    {
        return $this->render('errors/404', ['message' => $message])
            ->setStatusCode(404);
    }
}
