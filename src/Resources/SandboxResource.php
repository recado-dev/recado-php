<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\SimulatedEvent;
use Recado\Sdk\Dto\SimulatedReply;
use Recado\Sdk\Http\HttpClient;

/**
 * The Sandbox resource: drive the real delivery pipeline from a sandbox
 * project's own API token by simulating provider/engagement events on a
 * message (POST /sandbox/messages/{uuid}/events) or a contact's reply to an
 * email (POST /sandbox/messages/{uuid}/reply).
 *
 * The route only exists for a sandbox token — a production token gets a bare
 * 404 — so simulated events can never touch production data.
 */
final readonly class SandboxResource
{
    public const string EVENT_DELIVERED = 'delivered';

    public const string EVENT_HARD_BOUNCE = 'hard_bounce';

    public const string EVENT_SOFT_BOUNCE = 'soft_bounce';

    public const string EVENT_COMPLAINT = 'complaint';

    public const string EVENT_OPEN = 'open';

    public const string EVENT_CLICK = 'click';

    public const string EVENT_READ = 'read';

    public function __construct(private HttpClient $http) {}

    /**
     * Simulate an event on a sandbox message.
     *
     * @param  string  $event  One of the EVENT_* constants (plain
     *                         strings are accepted too).
     * @param  int|null  $linkIndex  Which tracked link a click hit; index 0 is
     *                               valid and sent whenever non-null.
     * @param  string|null  $url  Explicit URL for a click, when not using an
     *                            index.
     */
    public function simulate(string $uuid, string $event, ?int $linkIndex = null, ?string $url = null): SimulatedEvent
    {
        $payload = ['event' => $event];

        if ($linkIndex !== null) {
            $payload['link_index'] = $linkIndex;
        }

        if ($url !== null) {
            $payload['url'] = $url;
        }

        $response = $this->http->post('sandbox/messages/'.rawurlencode($uuid).'/events', ['json' => $payload]);

        return SimulatedEvent::fromArray($response['data'] ?? []);
    }

    /**
     * Simulate the contact replying to a sandbox email
     * (POST /sandbox/messages/{uuid}/reply). The reply runs through the real
     * inbound pipeline, so an authenticated, non-auto reply fires the
     * `message.replied` webhook and starts `contact_replied` automations.
     *
     * Verdicts (`spf`, `dkim`, `dmarc`) are PASS, FAIL, GRAY or
     * PROCESSING_FAILED and default to PASS (authenticated).
     *
     * @param  array{text?: string, subject?: string, from_email?: string, from_name?: string, spf?: string, dkim?: string, dmarc?: string, auto_reply?: bool}  $options
     */
    public function simulateReply(string $uuid, array $options = []): SimulatedReply
    {
        $response = $this->http->post(
            'sandbox/messages/'.rawurlencode($uuid).'/reply',
            ['json' => (object) array_filter($options, static fn (mixed $value): bool => $value !== null)],
        );

        return SimulatedReply::fromArray($response['data'] ?? []);
    }
}
