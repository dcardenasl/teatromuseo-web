<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClient;
use App\Services\SiteEventService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SiteEventServiceTest extends CIUnitTestCase
{
    private function makeService(array $getReturn): SiteEventService
    {
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient->method('get')->willReturn($getReturn);

        return new SiteEventService($apiClient);
    }

    public function testGetEventFetchesByLocaleAwareSlugInOneCall(): void
    {
        $event = [
            'id' => 12,
            'uuid' => 'evt-12',
            'title' => 'Muestra de verano',
            'status' => 'published',
            'slug' => 'muestra-de-verano',
            'slugs' => ['es' => 'muestra-de-verano'],
            'localized' => [
                'title' => 'Muestra de verano',
            ],
        ];

        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient
            ->expects($this->once())
            ->method('get')
            ->with(
                'public-read/es/events/muestra-de-verano',
                ['fields' => 'id,uuid,title,event_type,description,slug,slugs,cover_file_id,cover_image,gallery_file_ids,gallery_images,translations,localized,occurrences,status,created_at,updated_at'],
                300,
                'events',
            )
            ->willReturn(['ok' => true, 'data' => $event, 'meta' => []]);

        $service = new SiteEventService($apiClient);
        $result = $service->getEvent('es', 'muestra-de-verano');

        $this->assertSame($event, $result);
    }

    public function testGetEventFetchesByUuid(): void
    {
        $event = [
            'id' => 24,
            'uuid' => 'event-24',
            'title' => 'Función especial',
            'status' => 'published',
            'localized' => [
                'title' => 'Función especial',
            ],
        ];

        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient
            ->expects($this->once())
            ->method('get')
            ->with(
                'public-read/es/events/event-24',
                ['fields' => 'id,uuid,title,event_type,description,slug,slugs,cover_file_id,cover_image,gallery_file_ids,gallery_images,translations,localized,occurrences,status,created_at,updated_at'],
                300,
                'events',
            )
            ->willReturn(['ok' => true, 'data' => $event, 'meta' => []]);

        $service = new SiteEventService($apiClient);
        $result = $service->getEvent('es', 'event-24');

        $this->assertSame($event, $result);
    }

    public function testGetEventReturnsNullWhenTheDomainReportsNotFound(): void
    {
        // The domain already excludes non-published events from the public
        // detail endpoint (404), so a failed lookup — not a draft payload —
        // is the only shape SiteEventService needs to handle here.
        $service = $this->makeService([
            'ok' => false,
            'data' => null,
            'meta' => [],
        ]);

        $this->assertNull($service->getEvent('es', 'evento-privado'));
    }

    public function testListEventsUsesTheEventDomainEndpoint(): void
    {
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient
            ->expects($this->once())
            ->method('get')
            ->with(
                'public-read/es/events',
                [
                    'page' => 2,
                    'per_page' => 12,
                    'sort' => 'agenda',
                    'fields' => 'id,uuid,title,event_type,slug,cover_file_id,cover_image,localized,next_occurrence_at,status',
                ],
                180,
                'events',
            )
            ->willReturn(['ok' => true, 'data' => [], 'meta' => ['total' => 24, 'page' => 2, 'per_page' => 12]]);

        $service = new SiteEventService($apiClient);
        $result = $service->listEvents('es', ['page' => 2, 'per_page' => 12]);

        $this->assertSame([], $result['data']);
        $this->assertSame([
            'pagination' => [
                'total' => 24,
                'total_items' => 24,
                'page' => 2,
                'current_page' => 2,
                'per_page' => 12,
                'total_pages' => 2,
                'has_next_page' => false,
                'has_previous_page' => true,
            ],
        ], $result['meta']);
    }

    public function testListEventsReturnsEmptyEnvelopeOnFailure(): void
    {
        $service = $this->makeService([
            'ok' => false,
            'data' => null,
            'meta' => [],
        ]);

        $result = $service->listEvents('es');

        $this->assertSame([], $result['data']);
        $this->assertSame([], $result['meta']);
    }

    public function testListEventTypesDiscoversDistinctPublishedTypes(): void
    {
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient
            ->expects($this->once())
            ->method('get')
            ->with('public/events/types', [], 600, 'event_types')
            ->willReturn([
                'ok' => true,
                'data' => [
                    ['slug' => 'function', 'name' => 'Función', 'sort_order' => 10],
                    ['slug' => 'workshop', 'localized' => ['name' => 'Taller'], 'sort_order' => 40],
                ],
                'meta' => [],
            ]);

        $service = new SiteEventService($apiClient);

        $this->assertSame([
            ['slug' => 'function', 'name' => 'Función', 'sort_order' => 10],
            ['slug' => 'workshop', 'name' => 'Taller', 'sort_order' => 40],
        ], $service->listEventTypes('es'));
    }
}
