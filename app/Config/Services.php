<?php

declare(strict_types=1);

namespace Config;

use App\Libraries\BlockRenderer;
use App\Libraries\CacheInvalidator;
use App\Libraries\WebApiClient;
use App\Libraries\WebApiClientInterface;
use App\Services\SiteCategoryService;
use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;
use App\Services\SiteFormService;
use App\Services\SiteLanguageService;
use App\Services\SiteMenuService;
use App\Services\SitePageService;
use App\Services\SiteRedirectService;
use App\Services\SiteSettingsService;
use App\Services\SiteTagService;
use App\Services\SocialLinksService;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function webApiClient(bool $getShared = true): WebApiClientInterface
    {
        if ($getShared) {
            /** @var WebApiClientInterface */
            return static::getSharedInstance('webApiClient');
        }

        $config = config('App');

        return new WebApiClient(
            $config->webApiBaseUrl,
            $config->webApiKey,
            $config->webApiTimeout,
            $config->webApiStaleTtl
        );
    }

    public static function siteSettingsService(bool $getShared = true): SiteSettingsService
    {
        if ($getShared) {
            /** @var SiteSettingsService */
            return static::getSharedInstance('siteSettingsService');
        }

        return new SiteSettingsService(static::webApiClient());
    }

    public static function siteLanguageService(bool $getShared = true): SiteLanguageService
    {
        if ($getShared) {
            /** @var SiteLanguageService */
            return static::getSharedInstance('siteLanguageService');
        }

        return new SiteLanguageService(static::webApiClient());
    }

    public static function siteMenuService(bool $getShared = true): SiteMenuService
    {
        if ($getShared) {
            /** @var SiteMenuService */
            return static::getSharedInstance('siteMenuService');
        }

        return new SiteMenuService(static::webApiClient());
    }

    public static function sitePageService(bool $getShared = true): SitePageService
    {
        if ($getShared) {
            /** @var SitePageService */
            return static::getSharedInstance('sitePageService');
        }

        return new SitePageService(static::webApiClient());
    }

    public static function siteCollectionService(bool $getShared = true): SiteCollectionService
    {
        if ($getShared) {
            /** @var SiteCollectionService */
            return static::getSharedInstance('siteCollectionService');
        }

        return new SiteCollectionService(static::webApiClient());
    }

    public static function siteEntryService(bool $getShared = true): SiteEntryService
    {
        if ($getShared) {
            /** @var SiteEntryService */
            return static::getSharedInstance('siteEntryService');
        }

        return new SiteEntryService(static::webApiClient());
    }

    public static function siteCategoryService(bool $getShared = true): SiteCategoryService
    {
        if ($getShared) {
            /** @var SiteCategoryService */
            return static::getSharedInstance('siteCategoryService');
        }

        return new SiteCategoryService(static::webApiClient());
    }

    public static function siteTagService(bool $getShared = true): SiteTagService
    {
        if ($getShared) {
            /** @var SiteTagService */
            return static::getSharedInstance('siteTagService');
        }

        return new SiteTagService(static::webApiClient());
    }

    public static function siteRedirectService(bool $getShared = true): SiteRedirectService
    {
        if ($getShared) {
            /** @var SiteRedirectService */
            return static::getSharedInstance('siteRedirectService');
        }

        return new SiteRedirectService(static::webApiClient());
    }

    public static function blockRenderer(bool $getShared = true): BlockRenderer
    {
        if ($getShared) {
            /** @var BlockRenderer */
            return static::getSharedInstance('blockRenderer');
        }

        return new BlockRenderer();
    }

    public static function cacheInvalidator(bool $getShared = true): CacheInvalidator
    {
        if ($getShared) {
            /** @var CacheInvalidator */
            return static::getSharedInstance('cacheInvalidator');
        }

        return new CacheInvalidator();
    }

    public static function siteFormService(bool $getShared = true): SiteFormService
    {
        if ($getShared) {
            /** @var SiteFormService */
            return static::getSharedInstance('siteFormService');
        }

        return new SiteFormService(static::webApiClient());
    }

    public static function socialLinksService(bool $getShared = true): SocialLinksService
    {
        if ($getShared) {
            /** @var SocialLinksService */
            return static::getSharedInstance('socialLinksService');
        }

        return new SocialLinksService(static::webApiClient());
    }
}
