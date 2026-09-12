<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\SendingDomain;
use Recado\Sdk\Http\HttpClient;

/**
 * The Sending domains resource: the project's sending identities.
 *
 * This is what makes AUTOMATED onboarding possible — add the domain, read back
 * the DNS records to publish, pipe them into your DNS provider, poll the
 * verification, clean up — without anyone opening the dashboard.
 *
 * Every representation performs live DNS lookups, so treat these calls as a
 * status poll, not a hot path.
 *
 * **Not available in a sandbox.** A sandbox never sends externally, so it has
 * no sending identities and its credential must not reach the production
 * project's: every method here refuses a sandbox token with the code
 * `not_available_in_sandbox` (`RecadoException::isNotAvailableInSandbox()`).
 *
 * Skipping a warm-up ramp and resuming a breaker-paused identity have no API
 * and none is faked here: they are judgement calls about sending reputation and
 * stay in the dashboard, where the trip rates sit in front of a human.
 */
final readonly class SendingDomainsResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * Every sending domain of the project, alphabetically
     * (GET /sending-domains).
     *
     * Not paginated: a project holds a handful of identities.
     *
     * @return array<int, SendingDomain>
     */
    public function list(): array
    {
        $response = $this->http->get('sending-domains');

        $domains = [];

        foreach ($response['data'] ?? [] as $domain) {
            if (is_array($domain)) {
                $domains[] = SendingDomain::fromArray($domain);
            }
        }

        return $domains;
    }

    /**
     * Fetch one sending domain (GET /sending-domains/{id}).
     *
     * `404` `sending_domain_not_found` for an unknown or cross-project id.
     */
    public function get(int $id): SendingDomain
    {
        $response = $this->http->get('sending-domains/'.$id);

        return SendingDomain::fromArray($response['data'] ?? []);
    }

    /**
     * Add a domain and create its identity on the active provider
     * (POST /sending-domains, `201`).
     *
     * The domain is trimmed and lowercased before validation, and the answer
     * already carries the records to publish, so the next step is mechanical.
     *
     * Three refusals, all `422` with a code: `verification_unsupported` (the
     * active provider has no domain API — generic SMTP, or Postmark without the
     * optional account token — so the row could never leave `pending` and is
     * refused rather than created dead), `already_added` (this project already
     * carries the domain; a different project may still add it) and
     * `provider_error` (the provider rejected the creation — worth retrying).
     */
    public function add(string $domain): SendingDomain
    {
        $response = $this->http->post('sending-domains', ['json' => ['domain' => $domain]]);

        return SendingDomain::fromArray($response['data'] ?? []);
    }

    /**
     * Re-run the verification against the provider and return the refreshed
     * domain (POST /sending-domains/{id}/check).
     *
     * A POLL, not a promise: the domain stays `pending` until the provider
     * itself sees your records, and DNS propagation takes as long as it takes.
     * If the provider cannot be reached at all the stored status is left
     * untouched and you get `422` `verification_failed`, which is worth
     * retrying.
     */
    public function check(int $id): SendingDomain
    {
        $response = $this->http->post('sending-domains/'.$id.'/check');

        return SendingDomain::fromArray($response['data'] ?? []);
    }

    /**
     * Delete the domain and its provider identity
     * (DELETE /sending-domains/{id}, `204`).
     */
    public function delete(int $id): void
    {
        $this->http->delete('sending-domains/'.$id);
    }
}
