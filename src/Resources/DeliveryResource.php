<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\DeliveryHealth;
use Recado\Sdk\Http\HttpClient;

/**
 * The Delivery resource (read-only): the project's sending reputation.
 *
 * Read-only by design — resuming a breaker-paused identity and skipping
 * warm-up stay HUMAN actions in the dashboard, so the SDK exposes no method
 * for them.
 */
final readonly class DeliveryResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * The sender-health snapshot (GET /delivery/health).
     *
     * Not available in a sandbox: an intercepted project never sends
     * externally, so it has no sending reputation and its credential must not
     * read the production project's. A sandbox token gets a `ValidationException`
     * whose `getErrorCode()` is `not_available_in_sandbox`.
     */
    public function health(): DeliveryHealth
    {
        $response = $this->http->get('delivery/health');

        return DeliveryHealth::fromArray($response['data'] ?? []);
    }
}
