<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\EventOccurrence;
use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\RateLimitException;
use Recado\Sdk\Exception\ValidationException;

final class EventsTest extends TestCase
{
    public function test_list_parses_occurrences_and_passes_the_filters(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->occurrence()],
                'meta' => ['current_page' => 1, 'total' => 1],
                'links' => [],
            ]),
        ], $history);

        $page = $client->events()->list([
            'event' => 'order.completed',
            'since' => '2026-09-01T00:00:00Z',
            'per_page' => 50,
        ]);

        $this->assertCount(1, $page->data);
        $this->assertContainsOnlyInstancesOf(EventOccurrence::class, $page->data);

        $occurrence = $page->data[0];
        $this->assertSame(9190, $occurrence->id);
        $this->assertSame(15, $occurrence->eventId);
        $this->assertSame('order.completed', $occurrence->eventName);
        $this->assertSame('grace@example.com', $occurrence->contactEmail);
        $this->assertSame('8b2c1d44', $occurrence->contactUuid);
        $this->assertSame(4200, $occurrence->data['total']);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/events', $request->getUri()->getPath());

        $query = urldecode($request->getUri()->getQuery());
        $this->assertStringContainsString('event=order.completed', $query);
        $this->assertStringContainsString('since=2026-09-01T00:00:00Z', $query);
    }

    public function test_an_occurrence_without_a_payload_yields_an_empty_array(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->occurrence(['data' => []])],
                'meta' => [],
                'links' => [],
            ]),
        ], $history);

        $this->assertSame([], $client->events()->list()->data[0]->data);
    }

    public function test_cursor_walks_all_pages(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->occurrence(['id' => 1]), $this->occurrence(['id' => 2])],
                'meta' => ['current_page' => 1, 'last_page' => 2, 'per_page' => 2, 'total' => 3],
                'links' => ['next' => 'next-url'],
            ]),
            $this->jsonResponse(200, [
                'data' => [$this->occurrence(['id' => 3])],
                'meta' => ['current_page' => 2, 'last_page' => 2, 'per_page' => 2, 'total' => 3],
                'links' => ['next' => null],
            ]),
        ], $history);

        $ids = [];

        foreach ($client->events()->cursor(['per_page' => 2]) as $occurrence) {
            $ids[] = $occurrence->id;
        }

        $this->assertSame([1, 2, 3], $ids);
        $this->assertCount(2, $history);
    }

    public function test_for_contact_hits_the_contact_scoped_endpoint(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->occurrence()],
                'meta' => [],
                'links' => [],
            ]),
        ], $history);

        $page = $client->events()->forContact('ada+dev@example.com', ['event' => 'user.registered']);

        $this->assertCount(1, $page->data);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/contacts/ada%2Bdev%40example.com/events', $request->getUri()->getPath());
        $this->assertStringContainsString('event=user.registered', urldecode($request->getUri()->getQuery()));
    }

    public function test_for_contact_cursor_walks_all_pages(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->occurrence(['id' => 1])],
                'meta' => ['current_page' => 1, 'last_page' => 2, 'per_page' => 1, 'total' => 2],
                'links' => ['next' => 'next-url'],
            ]),
            $this->jsonResponse(200, [
                'data' => [$this->occurrence(['id' => 2])],
                'meta' => ['current_page' => 2, 'last_page' => 2, 'per_page' => 1, 'total' => 2],
                'links' => ['next' => null],
            ]),
        ], $history);

        $ids = [];

        foreach ($client->events()->forContactCursor('ada@example.com', ['per_page' => 1]) as $occurrence) {
            $ids[] = $occurrence->id;
        }

        $this->assertSame([1, 2], $ids);
        $this->assertStringContainsString('page=2', $history[1]['request']->getUri()->getQuery());
    }

    public function test_an_unknown_event_name_raises_a_not_found_exception_with_its_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Unknown event.', 'code' => 'event_not_found']),
        ], $history);

        try {
            $client->events()->list(['event' => 'never.recorded']);
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('event_not_found', $e->getErrorCode());
        }
    }

    public function test_a_relative_date_filter_raises_a_validation_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The given data was invalid.',
                'errors' => ['since' => ['The since field must be a valid date.']],
            ]),
        ], $history);

        try {
            $client->events()->list(['since' => 'yesterday']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('since', $e->errors());
        }
    }

    public function test_a_429_maps_to_the_rate_limit_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(429, ['message' => 'Too Many Requests'], ['Retry-After' => '7']),
        ], $history);

        try {
            $client->events()->forContact('ada@example.com');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(7, $e->retryAfter());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function occurrence(array $overrides = []): array
    {
        return array_merge([
            'id' => 9190,
            'event' => ['id' => 15, 'name' => 'order.completed'],
            'contact' => ['uuid' => '8b2c1d44', 'email' => 'grace@example.com'],
            'data' => ['total' => 4200, 'currency' => 'eur'],
            'created_at' => '2026-09-09T12:31:07+00:00',
        ], $overrides);
    }
}
