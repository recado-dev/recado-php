<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Segment;
use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\RateLimitException;
use Recado\Sdk\Exception\ValidationException;

final class SegmentsTest extends TestCase
{
    public function test_list_parses_paginated_segments_without_a_count(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->segment()],
                'meta' => ['current_page' => 1, 'total' => 1],
                'links' => [],
            ]),
        ], $history);

        $page = $client->segments()->list(['per_page' => 50]);

        $this->assertCount(1, $page->data);
        $this->assertContainsOnlyInstancesOf(Segment::class, $page->data);
        $this->assertSame(7, $page->data[0]->id);
        $this->assertSame('Active EU subscribers', $page->data[0]->name);
        $this->assertSame('all', $page->data[0]->conditions['match']);
        $this->assertNull($page->data[0]->contactsCount, 'The list endpoint omits contacts_count.');

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/segments', $request->getUri()->getPath());
        $this->assertStringContainsString('per_page=50', $request->getUri()->getQuery());
    }

    public function test_cursor_walks_all_pages(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->segment(['id' => 1]), $this->segment(['id' => 2])],
                'meta' => ['current_page' => 1, 'last_page' => 2, 'per_page' => 2, 'total' => 3],
                'links' => ['next' => 'next-url'],
            ]),
            $this->jsonResponse(200, [
                'data' => [$this->segment(['id' => 3])],
                'meta' => ['current_page' => 2, 'last_page' => 2, 'per_page' => 2, 'total' => 3],
                'links' => ['next' => null],
            ]),
        ], $history);

        $ids = [];

        foreach ($client->segments()->cursor(['per_page' => 2]) as $segment) {
            $ids[] = $segment->id;
        }

        $this->assertSame([1, 2, 3], $ids);
        $this->assertCount(2, $history);
        $this->assertStringContainsString('page=2', $history[1]['request']->getUri()->getQuery());
    }

    public function test_get_parses_the_live_contacts_count(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->segment(['contacts_count' => 1840])]),
        ], $history);

        $segment = $client->segments()->get(7);

        $this->assertSame(1840, $segment->contactsCount);
        $this->assertSame('/api/v1/segments/7', $history[0]['request']->getUri()->getPath());
    }

    public function test_create_posts_name_and_conditions(): void
    {
        $conditions = [
            'match' => 'all',
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
                [
                    'match' => 'any',
                    'conditions' => [
                        ['field' => 'tags', 'operator' => 'has', 'value' => 'vip'],
                    ],
                ],
            ],
        ];

        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->segment([
                'id' => 12,
                'name' => 'VIPs',
                'conditions' => $conditions,
                'contacts_count' => 96,
            ])]),
        ], $history);

        $segment = $client->segments()->create('VIPs', $conditions);

        $this->assertSame(12, $segment->id);
        $this->assertSame(96, $segment->contactsCount);
        $this->assertSame('any', $segment->conditions['conditions'][1]['match']);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/segments', $request->getUri()->getPath());
        $this->assertSame(
            ['name' => 'VIPs', 'conditions' => $conditions],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function test_update_patches_only_the_given_fields(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->segment(['name' => 'Renamed'])]),
        ], $history);

        $segment = $client->segments()->update(7, ['name' => 'Renamed']);

        $this->assertSame('Renamed', $segment->name);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/segments/7', $request->getUri()->getPath());
        $this->assertSame(['name' => 'Renamed'], json_decode((string) $request->getBody(), true));
    }

    public function test_delete_issues_a_delete(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(204, []),
        ], $history);

        $client->segments()->delete(7);

        $this->assertSame('DELETE', $history[0]['request']->getMethod());
        $this->assertSame('/api/v1/segments/7', $history[0]['request']->getUri()->getPath());
    }

    public function test_an_invalid_condition_tree_raises_a_validation_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The given data was invalid.',
                'errors' => ['conditions' => ['The conditions field is invalid.']],
            ]),
        ], $history);

        try {
            $client->segments()->create('Broken', ['match' => 'nope', 'conditions' => []]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conditions', $e->errors());
        }
    }

    public function test_an_unknown_segment_raises_a_not_found_exception_with_its_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Segment not found.', 'code' => 'segment_not_found']),
        ], $history);

        try {
            $client->segments()->get(99);
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('segment_not_found', $e->getErrorCode());
        }
    }

    public function test_a_429_maps_to_the_rate_limit_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(429, ['message' => 'Too Many Requests'], ['Retry-After' => '12']),
        ], $history);

        try {
            $client->segments()->list();
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(12, $e->retryAfter());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function segment(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'name' => 'Active EU subscribers',
            'conditions' => [
                'match' => 'all',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
                ],
            ],
            'created_at' => '2026-06-01T10:00:00+00:00',
            'updated_at' => '2026-06-01T10:00:00+00:00',
        ], $overrides);
    }
}
