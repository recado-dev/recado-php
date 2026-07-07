<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\SimulatedEvent;
use Recado\Sdk\Http\HttpClient;

/**
 * The Sandbox resource: drive the real delivery pipeline from a sandbox
 * project's own API token by simulating provider/engagement events on a
 * message (POST /sandbox/messages/{uuid}/events).
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

    public function __construct(private HttpClient $http)
    {
    }

    /**
     * Simulate an event on a sandbox message.
     *
     * @param string      $event     One of the EVENT_* constants (plain
     *                                strings are accepted too).
     * @param int|null    $linkIndex Which tracked link a click hit; index 0 is
     *                                valid and sent whenever non-null.
     * @param string|null $url       Explicit URL for a click, when not using an
     *                                index.
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
}
