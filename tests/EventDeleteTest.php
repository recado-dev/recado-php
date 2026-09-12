<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\UnsupportedFeatureException;

/**
 * DELETE /events/{name} — irreversible, and it takes the occurrence log with it.
 */
final class EventDeleteTest extends TestCase
{
    public function test_it_deletes_an_event_by_name(): void
    {
        $history = [];
        $client = $this->clientWithResponses([$this->jsonResponse(204, [])], $history);

        $client->events()->delete('user.registered');

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/api/v1/events/user.registered', $request->getUri()->getPath());
    }

    public function test_an_unknown_name_is_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Event not found.', 'code' => 'event_not_found']),
        ], $history);

        try {
            $client->events()->delete('never.recorded');
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('event_not_found', $e->getErrorCode());
        }
    }

    /**
     * A slash is a path separator and an encoded one is not reliably decoded,
     * so the SDK refuses locally rather than risk deleting the wrong event.
     */
    public function test_a_name_with_a_slash_is_refused_before_any_request(): void
    {
        $history = [];
        $client = $this->clientWithResponses([], $history);

        $this->expectException(UnsupportedFeatureException::class);

        try {
            $client->events()->delete('orders/created');
        } finally {
            $this->assertSame([], $history);
        }
    }
}
