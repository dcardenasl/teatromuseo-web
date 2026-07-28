<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class MuseumController extends BasePublicWebController
{
    /**
     * Render the public listing of the museum catalog collection.
     */
    public function index(): ResponseInterface
    {
        $lang = $this->request->getLocale();
        $catalogService = Services::siteCatalogService();

        // Get filter inputs safely
        $searchVal = $this->request->getGet('search');
        $search = is_string($searchVal) ? trim($searchVal) : '';

        $categoryVal = $this->request->getGet('category');
        $categoryId = is_string($categoryVal) ? trim($categoryVal) : '';

        $pageVal = $this->request->getGet('page');
        $page = is_numeric($pageVal) ? (int) $pageVal : 1;

        $perPage = 12; // Premium card layout looks best with 12 items per page

        // Build query params for the catalog domain API
        $queryParams = [
            'page'     => $page,
            'per_page' => $perPage,
        ];

        if ($search !== '') {
            $queryParams['search'] = $search;
        }

        // Apply category filter if set
        $filter = [];
        if ($categoryId !== '') {
            $filter['category_id'] = $categoryId;
        }
        $queryParams['filter'] = $filter;

        // Fetch data
        $categories = $catalogService->listCategories($lang);
        $result = $catalogService->listItems($lang, $queryParams);

        $items = $result['data'] ?? [];
        $meta = $result['meta'] ?? [];
        $pagination = $meta['pagination'] ?? [];

        return $this->render('museum/index', [
            'title'           => lang('Site.museum_collection_title') ?: 'Colección de Museo',
            'categories'      => $categories,
            'currentCategory' => $categoryId,
            'search'          => $search,
            'data'            => $items,
            'currentPage'     => $page,
            'pagination'      => $pagination,
            'lang'            => $lang,
        ]);
    }

    /**
     * Render details of a single collection item (scientific sheet).
     */
    public function show(string $idOrCode): ResponseInterface
    {
        $lang = $this->request->getLocale();
        $catalogService = Services::siteCatalogService();

        $item = $catalogService->getItem($lang, $idOrCode);

        if (!$item) {
            return $this->notFound(lang('Site.collection_item_not_found') ?: 'Pieza de colección no encontrada');
        }

        // Fetch all categories to match the category name
        $categories = $catalogService->listCategories($lang);
        $categoryName = '';
        foreach ($categories as $cat) {
            if ((int) ($cat['id'] ?? 0) === (int) ($item['category_id'] ?? 0)) {
                $categoryName = (string) ($cat['name'] ?? '');
                break;
            }
        }

        return $this->render('museum/show', [
            'title'           => (string) ($item['name'] ?? ''),
            'item'            => $item,
            'categoryName'    => $categoryName,
            'lang'            => $lang,
        ]);
    }
}
