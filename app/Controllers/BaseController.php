<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // CMS/API is the source of truth. Keep the config list only as a
        // safe fallback when the public API is unavailable. setLocale() and
        // setValidLocales() only exist on IncomingRequest (not the CLI
        // variant), so narrow the type before calling them.
        if ($request instanceof \CodeIgniter\HTTP\IncomingRequest) {
            try {
                $codes = \Config\Services::siteLanguageService()->getCodes();
                if ($codes !== []) {
                    $config = config('App');
                    $config->supportedLocales = $codes;
                    $default = \Config\Services::siteLanguageService()->getDefaultCode();
                    if ($default !== null) {
                        $config->defaultLocale = $default;
                    }

                    // CodeIgniter validates setLocale() against the request's
                    // own list, which is initialized before this controller
                    // runs. Keep that list in sync with the CMS or a dynamic
                    // locale (for example `fr`) is silently reset to `es`.
                    $request->setValidLocales($codes);

                    $requestedLocale = strtolower((string) $request->getUri()->getSegment(1));
                    $request->setLocale(in_array($requestedLocale, $codes, true) ? $requestedLocale : ($default ?? $codes[0]));
                }
            } catch (\Throwable $exception) {
                log_message('warning', 'Dynamic language discovery unavailable: {message}', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }
}
