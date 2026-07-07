<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Resources\SandboxResource;

final class SandboxTest extends TestCase
{
    public function test_simulate_minimal_body_has_no_null_keys(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['message' => 'msg-uuid', 'event' => 'delivered', 'status' => 'delivered'],
            ]),
        ], $history);

        $result = $client->sandbox()->simulate('msg-uuid', SandboxResource::EVENT_DELIVERED);

        $this->assertSame('msg-uuid', $result->message);
        $this->assertSame('delivered', $result->event);
        $this->assertSame('delivered', $result->status);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/sandbox/messages/msg-uuid/events', $request->getUri()->getPath());
        $this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(['event' => 'delivered'], $body);
        $this->assertArrayNotHasKey('link_index', $body);
        $this->assertArrayNotHasKey('url', $body);
    }

    public function test_simulate_sends_link_index_zero(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['message' => 'msg-uuid', 'event' => 'click', 'status' => 'delivered'],
            ]),
        ], $history);

        $client->sandbox()->simulate('msg-uuid', SandboxResource::EVENT_CLICK, linkIndex: 0);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayHasKey('link_index', $body);
        $this->assertSame(0, $body['link_index']);
    }

    public function test_simulate_sends_url_variant(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, [
                'data' => ['message' => 'msg-uuid', 'event' => 'click', 'status' => 'delivered'],
            ]),
        ], $history);

        $client->sandbox()->simulate('msg-uuid', SandboxResource::EVENT_CLICK, url: 'https://example.com/x');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('https://example.com/x', $body['url']);
        $this->assertArrayNotHasKey('link_index', $body);
    }

    public function test_simulate_invalid_event_maps_to_validation_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'This event cannot be simulated on this channel.',
                'code' => 'invalid_event_for_channel',
            ]),
        ], $history);

        try {
            $client->sandbox()->simulate('msg-uuid', SandboxResource::EVENT_OPEN);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('invalid_event_for_channel', $e->getErrorCode());
            $this->assertSame(422, $e->getStatus());
        }
    }

    public function test_simulate_missing_link_maps_to_validation_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The message has no tracked link at that index.',
                'code' => 'link_index',
            ]),
        ], $history);

        try {
            $client->sandbox()->simulate('msg-uuid', SandboxResource::EVENT_CLICK, linkIndex: 9);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('link_index', $e->getErrorCode());
        }
    }

    public function test_simulate_unknown_message_maps_to_not_found_with_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, [
                'message' => 'Message not found.',
                'code' => 'message_not_found',
            ]),
        ], $history);

        try {
            $client->sandbox()->simulate('missing-uuid', SandboxResource::EVENT_DELIVERED);
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('message_not_found', $e->getErrorCode());
            $this->assertSame(404, $e->getStatus());
        }
    }

    public function test_simulate_production_token_maps_to_bare_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, []),
        ], $history);

        try {
            $client->sandbox()->simulate('msg-uuid', SandboxResource::EVENT_DELIVERED);
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame(404, $e->getStatus());
            $this->assertNull($e->getErrorCode());
        }
    }
}
