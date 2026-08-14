<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\BlockPrefetch\BlockDependencyResolver;
use App\Services\BlockPrefetch\BlockPlanCollector;
use App\Services\BlockPrefetch\BlockRequestPlanner;
use App\Services\BlockPrefetch\BlockResultMaterializer;
use App\Services\BlockPrefetch\ListQueryBuilder;
use App\Services\BlockPrefetch\PrefetchRequestExecutor;
use App\Services\BlockPrefetch\PrefetchRequestQueue;
use App\Services\BlockPrefetch\RequestQueryReader;

/**
 * Plans and resolves every remote dependency of a page's dynamic blocks before
 * BlockRenderer starts. ViewModels consume the path-keyed envelopes produced by
 * this service and never perform a lazy HTTP fallback.
 *
 * This class is the thin public facade for the pipeline; the actual work is
 * split across single-purpose collaborators in `App\Services\BlockPrefetch`:
 *
 * - {@see BlockPlanCollector} walks the block tree into plans/form keys/cache scopes.
 * - {@see BlockRequestPlanner} decides, per plan, which requests to queue.
 * - {@see PrefetchRequestQueue} accumulates and deduplicates those requests.
 * - {@see PrefetchRequestExecutor} dispatches a queued batch (parallel when possible).
 * - {@see BlockDependencyResolver} unblocks plans whose listing request depended
 *   on a first-wave response (a resolved CMS collection, a resolved category).
 * - {@see BlockResultMaterializer} turns a resolved plan into the block-result
 *   envelope ViewModels consume.
 *
 * Two request waves happen at most: the first wave resolves detail lookups,
 * already-keyed lists and any CMS-collection/category dependency; the second
 * wave (only issued if the first added new requests) resolves the lists that
 * were waiting on that dependency.
 */
final class BlockPrefetchService
{
    /** @var array<string, WebApiClientInterface> */
    private readonly array $clients;

    private readonly BlockPlanCollector $plans;
    private readonly BlockRequestPlanner $requestPlanner;
    private readonly BlockDependencyResolver $dependencyResolver;
    private readonly BlockResultMaterializer $results;
    private readonly PrefetchRequestExecutor $executor;
    private readonly RequestQueryReader $requestQuery;

    public function __construct(WebApiClientInterface $client)
    {
        $this->clients = [
            'cms' => $client,
            'catalog' => $client,
            'event' => $client,
        ];

        $this->requestQuery = new RequestQueryReader();
        $this->results = new BlockResultMaterializer($this->requestQuery);
        $this->plans = new BlockPlanCollector($this->results);

        $queryBuilder = new ListQueryBuilder($this->requestQuery);
        $this->requestPlanner = new BlockRequestPlanner($this->clients, $queryBuilder, $this->results);
        $this->dependencyResolver = new BlockDependencyResolver($this->requestPlanner, $queryBuilder, $this->results);
        $this->executor = new PrefetchRequestExecutor($this->clients, $this->results);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<string, list<array<string, mixed>>> $seededItems Items
     * already loaded by the owning controller, grouped by source type.
     * @return array{block_prefetch: array<string, array<string, mixed>>, block_prefetch_complete: bool, form_definitions: array<string, array<string, mixed>|null>, cacheScopes: list<string>}
     */
    public function prefetchContext(array $blocks, string $locale = 'es', array $seededItems = []): array
    {
        [$blockResults, $formDefinitions] = $this->prefetchInternal($blocks, $locale, $seededItems, true);

        return [
            'block_prefetch' => $blockResults,
            'block_prefetch_complete' => true,
            'form_definitions' => $formDefinitions,
            'cacheScopes' => $this->plans->cacheScopes($blocks),
        ];
    }

    /**
     * Every planned dynamic block receives an explicit envelope, including
     * upstream failures. The renderer can therefore distinguish an empty result
     * from an absent plan without issuing a second request.
     *
     * @param list<array<string, mixed>> $blocks
     * @param array<string, list<array<string, mixed>>> $seededItems
     * @return array<string, array<string, mixed>>
     */
    public function prefetch(array $blocks, string $locale = 'es', array $seededItems = []): array
    {
        [$results] = $this->prefetchInternal($blocks, $locale, $seededItems, false);

        return $results;
    }

    /**
     * Keep forms in the same initial request plan as dynamic blocks. Dependency
     * waves still use the existing executor, so maxParallelRequests and the
     * current deadline/stale behavior remain unchanged.
     *
     * @param list<array<string, mixed>> $blocks
     * @param array<string, list<array<string, mixed>>> $seededItems
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>|null>}
     */
    private function prefetchInternal(array $blocks, string $locale, array $seededItems, bool $includeForms): array
    {
        $plans = $this->plans->collect($blocks, $locale);
        $queue = new PrefetchRequestQueue($locale, $this->requestQuery);

        foreach ($plans as &$plan) {
            $this->requestPlanner->planInitial($plan, $locale, $queue, $seededItems);
        }
        unset($plan);

        $formIndexes = [];
        if ($includeForms) {
            foreach ($this->plans->formKeys($blocks) as $formKey) {
                $formIndexes[$formKey] = $queue->add(
                    'cms',
                    'public/' . rawurlencode($locale) . '/forms/' . rawurlencode($formKey),
                    [],
                    300,
                    'forms',
                );
            }
        }

        $initialRequestCount = $queue->count();
        $responses = $this->executor->execute($queue->all());
        $this->dependencyResolver->resolve($plans, $responses, $locale, $queue);

        if ($queue->count() > $initialRequestCount) {
            $responses = array_replace(
                $responses,
                $this->executor->execute($queue->all(), $initialRequestCount),
            );
        }

        $results = [];
        foreach ($plans as $blockPath => $plan) {
            $results[(string) $blockPath] = $this->results->materialize($plan, $responses);
        }

        $formDefinitions = [];
        foreach ($formIndexes as $formKey => $index) {
            $response = $responses[$index] ?? null;
            $formDefinitions[$formKey] = is_array($response)
                && ($response['ok'] ?? false)
                && is_array($response['data'] ?? null)
                ? $response['data']
                : null;
        }

        return [$results, $formDefinitions];
    }
}
