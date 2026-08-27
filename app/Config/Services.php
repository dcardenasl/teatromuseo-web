<?php

declare(strict_types=1);

namespace Config;

use App\Analytics\CurlAnalyticsTransport;
use App\Libraries\AnalyticsQueue;
use App\Libraries\BlockRenderer;
use App\Libraries\CacheInvalidator;
use App\Libraries\HtmlResponseCacheRegistry;
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
use App\Services\PublicReadDiagnosticsService;
use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;
use App\Services\SiteFormService;
use App\Services\SitePageService;
use App\Services\SiteSettingsService;
use App\Services\SiteSitemapService;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function publicReadDiagnostics(bool $getShared = true): PublicReadDiagnosticsService
    {
        if ($getShared) {
            /** @var PublicReadDiagnosticsService */
            return static::getSharedInstance('publicReadDiagnostics');
        }

        return new PublicReadDiagnosticsService(static::bffWebApiClient());
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
                static::bffWebApiClient(),
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
                static::bffWebApiClient(),
            ),
            publisher: static::pageSnapshotStore(),
            lock: static::pageRegenerationLock(),
            clock: $clock,
            ttl: $config->pageSnapshotTtl,
            scopes: $config->pageSnapshotScopes,
        );
    }

    /**
     * Single public-read client. CMS, Catalog and Event paths are routed by the
     * BFF, so the website no longer needs one HTTP client per domain database.
     */
    public static function bffWebApiClient(bool $getShared = true): WebApiClientInterface
    {
        if ($getShared) {
            /** @var WebApiClientInterface */
            return static::getSharedInstance('bffWebApiClient');
        }

        $config = config('App');

        return new WebApiClient(
            $config->bffApiBaseUrl,
            $config->bffApiKey,
            $config->webApiTimeout,
            $config->webApiStaleTtl,
            $config->webApiConnectTimeout,
        );
    }

    public static function analyticsQueue(bool $getShared = true): AnalyticsQueue
    {
        if ($getShared) {
            /** @var AnalyticsQueue */
            return static::getSharedInstance('analyticsQueue');
        }

        $config = config('App');

        return new AnalyticsQueue(
            directory: $config->analyticsQueueDirectory,
            maxAttempts: $config->trackingQueueMaxAttempts,
            transport: new CurlAnalyticsTransport(
                trackUrl: rtrim($config->trackingApiBaseUrl, '/') . '/api/v1/public/track',
                apiKey: $config->webApiKey,
                timeoutMs: $config->trackingQueueTimeoutMs,
                connectTimeoutMs: $config->trackingQueueConnectTimeoutMs,
            ),
        );
    }

    public static function sitePageService(bool $getShared = true): SitePageService
    {
        if ($getShared) {
            /** @var SitePageService */
            return static::getSharedInstance('sitePageService');
        }

        return new SitePageService(static::bffWebApiClient());
    }

    public static function siteCollectionService(bool $getShared = true): SiteCollectionService
    {
        if ($getShared) {
            /** @var SiteCollectionService */
            return static::getSharedInstance('siteCollectionService');
        }

        return new SiteCollectionService(static::bffWebApiClient());
    }

    public static function siteEntryService(bool $getShared = true): SiteEntryService
    {
        if ($getShared) {
            /** @var SiteEntryService */
            return static::getSharedInstance('siteEntryService');
        }

        return new SiteEntryService(static::bffWebApiClient());
    }

    public static function siteSitemapService(bool $getShared = true): SiteSitemapService
    {
        if ($getShared) {
            /** @var SiteSitemapService */
            return static::getSharedInstance('siteSitemapService');
        }

        return new SiteSitemapService(static::bffWebApiClient());
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

    public static function htmlResponseCacheRegistry(bool $getShared = true): HtmlResponseCacheRegistry
    {
        if ($getShared) {
            /** @var HtmlResponseCacheRegistry */
            return static::getSharedInstance('htmlResponseCacheRegistry');
        }

        return new HtmlResponseCacheRegistry(static::cache());
    }

    public static function siteFormService(bool $getShared = true): SiteFormService
    {
        if ($getShared) {
            /** @var SiteFormService */
            return static::getSharedInstance('siteFormService');
        }

        return new SiteFormService(static::bffWebApiClient());
    }

    public static function siteSettingsService(bool $getShared = true): SiteSettingsService
    {
        if ($getShared) {
            /** @var SiteSettingsService */
            return static::getSharedInstance('siteSettingsService');
        }

        return new SiteSettingsService(static::bffWebApiClient());
    }

}
