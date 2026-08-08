<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\ParallelAliasResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParallelAliasResolverTest extends TestCase
{
    private ParallelAliasResolver $resolver;
    private WebApiClientInterface&MockObject $apiClient;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(WebApiClientInterface::class);
        $this->resolver = new ParallelAliasResolver($this->apiClient);

        // Clear cache between tests - use wildcard to clear everything
        $cache = \Config\Services::cache();
        // Clear all possible cache patterns
        $cache->deleteMatching('*');
    }

    public function test_resolve_alias_single(): void
    {
        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 42, 'slug' => 'test-single-alias'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $result = $this->resolver->resolveAlias('test-single-alias', 'collection_items');

        $this->assertEquals('42', $result);
    }

    public function test_resolve_alias_unknown(): void
    {
        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $result = $this->resolver->resolveAlias('nonexistent-alias', 'collection_items');

        $this->assertNull($result);
    }

    public function test_resolve_batch_multiple(): void
    {
        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 42, 'slug' => 'test-batch-slug-1'],
                ['id' => 99, 'slug' => 'test-batch-slug-2'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $result = $this->resolver->resolveBatch(['test-batch-slug-1', 'test-batch-slug-2'], 'collection_items');

        $this->assertEquals('42', $result['test-batch-slug-1']);
        $this->assertEquals('99', $result['test-batch-slug-2']);
        $this->assertCount(2, $result);
    }

    public function test_resolve_batch_partial(): void
    {
        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 42, 'slug' => 'test-partial-slug-1'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $result = $this->resolver->resolveBatch(['test-partial-slug-1', 'test-partial-unknown'], 'collection_items');

        $this->assertEquals('42', $result['test-partial-slug-1']);
        $this->assertNull($result['test-partial-unknown']);
    }

    public function test_resolve_batch_empty(): void
    {
        $this->apiClient->expects($this->never())
            ->method('get');

        $result = $this->resolver->resolveBatch([], 'collection_items');

        $this->assertEmpty($result);
    }

    public function test_resolve_batch_unknown_type(): void
    {
        $this->apiClient->expects($this->never())
            ->method('get');

        $result = $this->resolver->resolveBatch(['payaso'], 'unknown_type');

        $this->assertArrayHasKey('payaso', $result);
        $this->assertNull($result['payaso']);
    }

    public function test_resolve_batch_deduplicates_aliases(): void
    {
        // Clear cache for this specific test
        \Config\Services::cache()->deleteMatching('alias_resolver_*');

        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 42, 'slug' => 'payaso-dedup'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with($this->stringContains('payaso-dedup'))
            ->willReturn($mockResponse);

        $result = $this->resolver->resolveBatch(['payaso-dedup', 'payaso-dedup', 'payaso-dedup'], 'collection_items');

        // Should only appear once in results
        $this->assertCount(1, $result);
        $this->assertEquals('42', $result['payaso-dedup']);
    }

    public function test_resolve_batch_handles_paginated_response(): void
    {
        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                'data' => [
                    ['id' => 42, 'slug' => 'test-paginated-slug-1'],
                    ['id' => 99, 'slug' => 'test-paginated-slug-2'],
                ],
                'meta' => ['current_page' => 1, 'total' => 2],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $result = $this->resolver->resolveBatch(['test-paginated-slug-1', 'test-paginated-slug-2'], 'collection_items');

        $this->assertEquals('42', $result['test-paginated-slug-1']);
        $this->assertEquals('99', $result['test-paginated-slug-2']);
    }

    public function test_resolve_batch_handles_missing_data(): void
    {
        // Clear cache for this specific test
        \Config\Services::cache()->deleteMatching('alias_resolver_*');

        $mockResponse = [
            'ok' => false,
            'status' => 404,
            'data' => null,
            'meta' => [],
            'messages' => ['Not found'],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);

        $result = $this->resolver->resolveBatch(['test-missing-data-slug'], 'collection_items');

        $this->assertNull($result['test-missing-data-slug']);
    }

    public function test_resolve_batch_filters_empty_strings(): void
    {
        $mockResponse = [
            'ok' => true,
            'status' => 200,
            'data' => [
                ['id' => 42, 'slug' => 'test-filter-slug'],
            ],
            'meta' => [],
            'messages' => [],
        ];

        $this->apiClient->expects($this->once())
            ->method('get')
            ->with($this->stringContains('test-filter-slug'))
            ->willReturn($mockResponse);

        $result = $this->resolver->resolveBatch(['test-filter-slug', '', null], 'collection_items');

        $this->assertArrayHasKey('test-filter-slug', $result);
        $this->assertEquals('42', $result['test-filter-slug']);
        $this->assertCount(1, $result);
    }
}
