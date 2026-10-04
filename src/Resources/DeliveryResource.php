<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\DeliveryHealth;
use Recado\Sdk\Dto\ReputationLimits;
use Recado\Sdk\Http\HttpClient;

/**
 * The Delivery resource: the project's sending reputation and the limits it
 * is judged with.
 *
 * Resuming a breaker-paused identity by hand and skipping warm-up stay HUMAN
 * actions in the dashboard, so the SDK exposes no method for them. The limits
 * (and the automatic-resume switch) are tunable: they are reversible and
 * reach nobody.
 *
 * Not available in a sandbox: an intercepted project never sends externally,
 * so it has no sending reputation and its credential must not read or tune
 * the production project's. A sandbox token is refused with the code
 * `not_available_in_sandbox` — branch on
 * `RecadoException::isNotAvailableInSandbox()`, not on the HTTP status.
 */
final readonly class DeliveryResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * The sender-health snapshot (GET /delivery/health).
     */
    public function health(): DeliveryHealth
    {
        $response = $this->http->get('delivery/health');

        return DeliveryHealth::fromArray($response['data'] ?? []);
    }

    /**
     * The thresholds the project's sending reputation is judged with
     * (GET /delivery/reputation-limits): the values in force, the project's
     * own overrides, the platform defaults and the bounds of an override.
     */
    public function reputationLimits(): ReputationLimits
    {
        $response = $this->http->get('delivery/reputation-limits');

        return ReputationLimits::fromArray($response['data'] ?? []);
    }

    /**
     * Override reputation limits (PATCH /delivery/reputation-limits) and get
     * the new snapshot back.
     *
     * Partial: only the keys you send change, and a `null` value CLEARS that
     * override (the platform default applies again). Rates are fractions
     * (`0.08` = 8%). `auto_resume_enabled` false makes every breaker pause of
     * the project manual-only; true or null follows the platform default.
     *
     * A value outside its bounds — or a `bounce_warning` that would not stay
     * below the `bounce_critical` in force after the write — is a
     * `ValidationException` with the usual `errors` map. A change takes effect
     * on the next evaluation and never resumes an identity already paused.
     *
     * @param  array<string, float|int|bool|null>  $changes  Any of `bounce_rate`,
     *                                                       `complaint_rate`, `min_sample`, `short_min_sample`, `min_bounces`,
     *                                                       `min_complaints`, `bounce_warning`, `bounce_critical`,
     *                                                       `auto_resume_enabled`.
     */
    public function updateReputationLimits(array $changes): ReputationLimits
    {
        $response = $this->http->patch('delivery/reputation-limits', ['json' => $changes]);

        return ReputationLimits::fromArray($response['data'] ?? []);
    }
}
