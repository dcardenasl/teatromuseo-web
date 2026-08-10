<?php

declare(strict_types=1);

namespace Config;

use App\Interfaces\AliasResolverInterface;
use App\Libraries\BlockRenderer;
use App\Libraries\CacheInvalidator;
use App\Libraries\PublicListingPageBuilder;
use App\Libraries\WebApiClient;
use App\Libraries\WebApiClientInterface;
use App\PageDelivery\FileRegenerationLock;
use App\PageDelivery\FileSnapshotStore;
use App\PageDelivery\NullRegenerationLock;
use App\PageDelivery\NullSnapshotStore;
use App\PageDelivery\PageDeliveryInterface;
use App\PageDelivery\PageDeliveryService;
use App\PageDelivery\RegenerationLockInterface;
use App\PageDelivery\SnapshotBuilder;
use App\PageDelivery\SnapshotBuilderInterface;
use App\PageDelivery\SnapshotPageDeliveryAdapter;
use App\PageDelivery\SnapshotPublisherInterface;
use App\PageDelivery\SynchronousPageDeliveryAdapter;
use App\PageDelivery\SystemClock;
use App\Services\BlockPrefetchService;
use App\Services\LayoutDataPrefetchService;
use App\Services\PageResolverService;
use App\Services\ParallelAliasResolver;
use App\Services\PublicReadDiagnosticsService;
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
    public static function publicReadDiagnostics(bool $getShared = true): PublicReadDiagnosticsService
    {
        if ($getShared) {
            /** @var PublicReadDiagnosticsService */
            return static::getSharedInstance('publicReadDiagnostics');
        }

        return new PublicReadDiagnosticsService(
            static::webApiClient(),
            static::catalogWebApiClient(),
            static::eventWebApiClient(),
        );
    }

    public static function pageDelivery(bool $getShared = true): PageDeliveryInterface
    {
        if ($getShared) {
            /** @var PageDeliveryInterface */
            return static::getSharedInstance('pageDelivery');
        }

        $config = config('App');
        $clock = new SystemClock();

        return new PageDeliveryService(
            synchronous: new SynchronousPageDeliveryAdapter(
                static::sitePageService(),
                static::layoutDataPrefetchService(),
                static::blockPrefetchService(),
                $clock,
            ),
            snapshot: new SnapshotPageDeliveryAdapter(
                static::pageSnapshotStore(),
                $clock,
                $config->pageSnapshotStaleTtl,
            ),
            lock: static::pageRegenerationLock(),
            mode: $config->pageDeliveryMode,
            allowSynchronousFallback: $config->pageDeliveryAllowSynchronousFallback,
            builder: static::snapshotBuilder(),
        );
    }

    public static function pageSnapshotStore(bool $getShared = true): SnapshotPublisherInterface
    {
        if ($getShared) {
            /** @var SnapshotPublisherInterface */
            return static::getSharedInstance('pageSnapshotStore');
        }

        $config = config('App');
        $directory = $config->pageSnapshotDirectory;

        return $directory !== '' && $config->pageSnapshotShared
            ? new FileSnapshotStore(
                $directory,
                $config->pageSnapshotMaxBytes,
                $config->pageSnapshotRetention,
                $config->pageSnapshotCompression,
            )
            : new NullSnapshotStore();
    }

    public static function pageRegenerationLock(bool $getShared = true): RegenerationLockInterface
    {
        if ($getShared) {
            /** @var RegenerationLockInterface */
            return static::getSharedInstance('pageRegenerationLock');
        }

        $config = config('App');
        $directory = $config->pageSnapshotDirectory;

        return $directory !== '' && $config->pageSnapshotShared
            ? new FileRegenerationLock($directory . DIRECTORY_SEPARATOR . 'locks', $config->pageSnapshotLockTtl)
            : new NullRegenerationLock();
    }

    public static function snapshotBuilder(bool $getShared = true): SnapshotBuilderInterface
    {
        if ($getShared) {
            /** @var SnapshotBuilderInterface */
            return static::getSharedInstance('snapshotBuilder');
        }

        $config = config('App');
        $clock = new SystemClock();

        return new SnapshotBuilder(
            synchronous: new SynchronousPageDeliveryAdapter(
                static::sitePageService(),
                static::layoutDataPrefetchService(),
                static::blockPrefetchService(),
                $clock,
            ),
            publisher: static::pageSnapshotStore(),
            lock: static::pageRegenerationLock(),
            clock: $clock,
            ttl: $config->pageSnapshotTtl,
            scopes: $config->pageSnapshotScopes,
        );
    }

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
            $config->webApiStaleTtl,
            $config->webApiMaxParallelRequests,
            $config->webApiConnectTimeout,
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

    public static function layoutDataPrefetchService(bool $getShared = true): LayoutDataPrefetchService
    {
        if ($getShared) {
            /** @var LayoutDataPrefetchService */
            return static::getSharedInstance('layoutDataPrefetchService');
        }

        return new LayoutDataPrefetchService(static::webApiClient());
    }

    public static function pageResolverService(bool $getShared = true): PageResolverService
    {
        if ($getShared) {
            /** @var PageResolverService */
            return static::getSharedInstance('pageResolverService');
        }

        return new PageResolverService(static::webApiClient());
    }

    public static function blockRenderer(bool $getShared = true): BlockRenderer
    {
        if ($getShared) {
            /** @var BlockRenderer */
            return static::getSharedInstance('blockRenderer');
        }

        return new BlockRenderer();
    }

    public static function blockPrefetchService(bool $getShared = true): BlockPrefetchService
    {
        if ($getShared) {
            /** @var BlockPrefetchService */
            return static::getSharedInstance('blockPrefetchService');
        }

        return new BlockPrefetchService([
            'cms' => static::webApiClient(),
            'catalog' => static::catalogWebApiClient(),
            'event' => static::eventWebApiClient(),
        ]);
    }

    public static function aliasResolverService(bool $getShared = true): AliasResolverInterface
    {
        if ($getShared) {
            /** @var AliasResolverInterface */
            return static::getSharedInstance('aliasResolverService');
        }

        return new ParallelAliasResolver(static::webApiClient());
    }

    public static function cacheInvalidator(bool $getShared = true): CacheInvalidator
    {
        if ($getShared) {
            /** @var CacheInvalidator */
            return static::getSharedInstance('cacheInvalidator');
        }

        return new CacheInvalidator();
    }

    public static function publicListingPageBuilder(bool $getShared = true): PublicListingPageBuilder
    {
        if ($getShared) {
            /** @var PublicListingPageBuilder */
            return static::getSharedInstance('publicListingPageBuilder');
        }

        return new PublicListingPageBuilder();
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

    public static function catalogWebApiClient(bool $getShared = true): WebApiClientInterface
    {
        if ($getShared) {
            /** @var WebApiClientInterface */
            return static::getSharedInstance('catalogWebApiClient');
        }

        $config = config('App');

        return new WebApiClient(
            $config->catalogApiBaseUrl,
            $config->webApiKey,
            $config->webApiTimeout,
            $config->webApiStaleTtl,
            $config->webApiMaxParallelRequests,
            $config->webApiConnectTimeout,
        );
    }

    public static function siteCatalogService(bool $getShared = true): \App\Services\SiteCatalogService
    {
        if ($getShared) {
            /** @var \App\Services\SiteCatalogService */
            return static::getSharedInstance('siteCatalogService');
        }

        return new \App\Services\SiteCatalogService(static::catalogWebApiClient());
    }

    public static function eventWebApiClient(bool $getShared = true): WebApiClientInterface
    {
        if ($getShared) {
            /** @var WebApiClientInterface */
            return static::getSharedInstance('eventWebApiClient');
        }

        $config = config('App');

        return new WebApiClient(
            $config->eventApiBaseUrl,
            $config->webApiKey,
            $config->webApiTimeout,
            $config->webApiStaleTtl,
            $config->webApiMaxParallelRequests,
            $config->webApiConnectTimeout,
        );
    }

    public static function siteEventService(bool $getShared = true): \App\Services\SiteEventService
    {
        if ($getShared) {
            /** @var \App\Services\SiteEventService */
            return static::getSharedInstance('siteEventService');
        }

        return new \App\Services\SiteEventService(static::eventWebApiClient());
    }
}
