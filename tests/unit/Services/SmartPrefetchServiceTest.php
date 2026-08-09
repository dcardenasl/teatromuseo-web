<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\SmartPrefetchService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SmartPrefetchServiceTest extends TestCase
{
    private SmartPrefetchService $service;
    private WebApiClientInterface&MockObject $apiClient;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(WebApiClientInterface::class);
        $this->service = new SmartPrefetchService([
            'cms' => $this->apiClient,
            'catalog' => $this->apiClient,
            'event' => $this->apiClient,
        ]);
    }

    public function test_prefetch_empty_requirements(): void
    {
        $result = $this->service->prefetch([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_prefetch_collection_items(): void
    {
        $requirements = [
            'collection_items' => [
                'ids' => [1, 2, 3],
                'fields' => ['id', 'name', 'slug'],
            ],
        ];

        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 1, 'name' => 'Item 1', 'slug' => 'item-1'],
                ['id' => 2, 'name' => 'Item 2', 'slug' => 'item-2'],
                ['id' => 3, 'name' => 'Item 3', 'slug' => 'item-3'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('multiGet')
            ->willReturn([$mockResponse]);

        $result = $this->service->prefetch($requirements);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertCount(3, $result['collection_items']);
        $this->assertArrayHasKey(1, $result['collection_items']);
        $this->assertEquals('Item 1', $result['collection_items'][1]['name']);
    }

    public function test_prefetch_events(): void
    {
        $requirements = [
            'events' => [
                'ids' => [10, 20],
                'fields' => ['id', 'title', 'slug'],
            ],
        ];

        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 10, 'title' => 'Festival', 'slug' => 'festival'],
                ['id' => 20, 'title' => 'Concert', 'slug' => 'concert'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('multiGet')
            ->willReturn([$mockResponse]);

        $result = $this->service->prefetch($requirements);

        $this->assertArrayHasKey('events', $result);
        $this->assertCount(2, $result['events']);
        $this->assertEquals('Festival', $result['events'][10]['title']);
    }

    public function test_prefetch_routes_each_resource_to_its_domain_client(): void
    {
        $catalogClient = $this->createMock(WebApiClientInterface::class);
        $eventClient = $this->createMock(WebApiClientInterface::class);

        $catalogClient->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static function (array $requests): bool {
                return ($requests[0]['path'] ?? '') === 'public/catalog/collection-items'
                    && ($requests[0]['query']['fields'] ?? '') === 'id'
                    && ($requests[0]['query']['filter']['id']['in'] ?? []) === [1];
            }))
            ->willReturn([[
                'ok' => true,
                'status' => 200,
                'data' => [['id' => 1, 'name' => 'Item']],
                'meta' => [],
                'messages' => [],
            ]]);

        $eventClient->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static function (array $requests): bool {
                return ($requests[0]['path'] ?? '') === 'public/events'
                    && ($requests[0]['query']['fields'] ?? '') === 'id'
                    && ($requests[0]['query']['filter']['id']['in'] ?? []) === [10];
            }))
            ->willReturn([[
                'ok' => true,
                'status' => 200,
                'data' => [['id' => 10, 'title' => 'Event']],
                'meta' => [],
                'messages' => [],
            ]]);

        $service = new SmartPrefetchService([
            'catalog' => $catalogClient,
            'event' => $eventClient,
        ]);

        $result = $service->prefetch([
            'collection_items' => ['ids' => [1], 'fields' => ['id']],
            'events' => ['ids' => [10], 'fields' => ['id']],
        ]);

        $this->assertArrayHasKey(1, $result['collection_items']);
        $this->assertArrayHasKey(10, $result['events']);
    }

    public function test_prefetch_multiple_resource_types(): void
    {
        $requirements = [
            'collection_items' => ['ids' => [1], 'fields' => ['id', 'name']],
            'events' => ['ids' => [10], 'fields' => ['id', 'title']],
        ];

        $this->apiClient->expects($this->exactly(2))
            ->method('multiGet')
            ->willReturnOnConsecutiveCalls(
                [[
                    'ok' => true,
                    'status' => 200,
                    'data' => [['id' => 1, 'name' => 'Item']],
                    'meta' => [],
                    'messages' => [],
                ]],
                [[
                    'ok' => true,
                    'status' => 200,
                    'data' => [['id' => 10, 'title' => 'Event']],
                    'meta' => [],
                    'messages' => [],
                ]],
            );

        $result = $this->service->prefetch($requirements);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertArrayHasKey('events', $result);
        $this->assertCount(1, $result['collection_items']);
        $this->assertCount(1, $result['events']);
    }

    public function test_prefetch_ignores_unknown_resource_types(): void
    {
        $requirements = [
            'unknown_type' => ['ids' => [1]],
            'collection_items' => ['ids' => [1]],
        ];

        $this->apiClient->expects($this->once())
            ->method('multiGet')
            ->willReturn([
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [['id' => 1, 'name' => 'Item']],
                    'meta' => [],
                    'messages' => [],
                ]
            ]);

        $result = $this->service->prefetch($requirements);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertArrayNotHasKey('unknown_type', $result);
    }

    public function test_prefetch_handles_missing_data(): void
    {
        $requirements = [
            'collection_items' => ['ids' => [1]],
        ];

        $this->apiClient->expects($this->once())
            ->method('multiGet')
            ->willReturn([
                [
                    'ok' => false,
                    'status' => 404,
                    'data' => null,
                    'meta' => [],
                    'messages' => ['Not found'],
                ]
            ]);

        $result = $this->service->prefetch($requirements);

        $this->assertEmpty($result);
    }

    public function test_prefetch_skips_empty_id_lists(): void
    {
        $requirements = [
            'collection_items' => ['ids' => [], 'fields' => ['id', 'name']],
        ];

        $this->apiClient->expects($this->never())
            ->method('multiGet');

        $result = $this->service->prefetch($requirements);

        $this->assertEmpty($result);
    }

    public function test_prefetch_handles_paginated_response(): void
    {
        $requirements = [
            'collection_items' => ['ids' => [1, 2]],
        ];

        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                'data' => [
                    ['id' => 1, 'name' => 'Item 1'],
                    ['id' => 2, 'name' => 'Item 2'],
                ],
                'meta' => ['current_page' => 1, 'total' => 2],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('multiGet')
            ->willReturn([$mockResponse]);

        $result = $this->service->prefetch($requirements);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertCount(2, $result['collection_items']);
    }

    public function test_prefetch_batch_returns_empty_for_unknown_type(): void
    {
        $result = $this->service->prefetchBatch('unknown_type', [1, 2]);

        $this->assertEmpty($result);
    }

    public function test_prefetch_batch_returns_empty_for_empty_ids(): void
    {
        $result = $this->service->prefetchBatch('collection_items', []);

        $this->assertEmpty($result);
    }

    public function test_prefetch_batch_collection_items(): void
    {
        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with($this->stringContains('public/catalog/collection-items'))
            ->willReturn($mockResponse);

        $result = $this->service->prefetchBatch('collection_items', [1, 2]);

        $this->assertCount(2, $result);
        $this->assertEquals('Item 1', $result[1]['name']);
    }

    public function test_prefetch_batch_filters_by_requested_ids(): void
    {
        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
                ['id' => 3, 'name' => 'Item 3'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        // Only requesting IDs 1 and 2
        $result = $this->service->prefetchBatch('collection_items', [1, 2]);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertArrayNotHasKey(3, $result);
    }

    public function test_prefetch_batch_with_custom_fields(): void
    {
        $this->apiClient->expects($this->once())
            ->method('get')
            ->with(
                'public/catalog/collection-items',
                $this->callback(static fn (array $query): bool => ($query['fields'] ?? '') === 'id,name,slug'),
                300,
                'collection_items',
            )
            ->willReturn(['ok' => true, 'status' => 200, 'data' => [], 'meta' => [], 'messages' => []]);

        $this->service->prefetchBatch('collection_items', [1], ['id', 'name', 'slug']);
    }

    public function test_prefetch_includes_sparse_fieldset_param(): void
    {
        $requirements = [
            'collection_items' => [
                'ids' => [1],
                'fields' => ['id', 'name'],
            ],
        ];

        $this->apiClient->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static function (array $requests): bool {
                return ($requests[0]['query']['fields'] ?? '') === 'id,name';
            }))
            ->willReturn([
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [['id' => 1, 'name' => 'Item']],
                    'meta' => [],
                    'messages' => [],
                ]
            ]);

        $this->service->prefetch($requirements);
    }
}
