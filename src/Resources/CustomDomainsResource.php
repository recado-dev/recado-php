<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\CustomDomain;
use Recado\Sdk\Http\HttpClient;

/**
 * The Custom domains resource: the project's own public hostname
 * (`news.customer.com`).
 *
 * **One domain per project.** The collection is a collection for forward
 * compatibility; today it holds 0 or 1 element.
 *
 * Adding requires the `custom_domains` plan entitlement (`422`
 * `custom_domains_not_entitled`). Checking and deleting stay ungated, so a
 * downgraded team keeps managing the domain it already has — it simply stops
 * being honored while unentitled.
 *
 * **Not available in a sandbox**: a sandbox never serves customer-facing pages,
 * so every method refuses a sandbox token with the code
 * `not_available_in_sandbox` (`RecadoException::isNotAvailableInSandbox()`).
 */
final readonly class CustomDomainsResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * The project's custom domain (GET /custom-domains).
     *
     * Returns 0 or 1 element — see the one-per-project rule above.
     *
     * @return array<int, CustomDomain>
     */
    public function list(): array
    {
        $response = $this->http->get('custom-domains');

        $domains = [];

        foreach ($response['data'] ?? [] as $domain) {
            if (is_array($domain)) {
                $domains[] = CustomDomain::fromArray($domain);
            }
        }

        return $domains;
    }

    /**
     * The project's custom domain, or null when it has none
     * (GET /custom-domains).
     *
     * The convenience shape for the one-per-project reality.
     */
    public function current(): ?CustomDomain
    {
        return $this->list()[0] ?? null;
    }

    /**
     * Add the domain (POST /custom-domains, `201`).
     *
     * The hostname is lowercased and trimmed for you and a trailing root dot is
     * removed. Refusals: `422` `custom_domains_not_entitled` (plan), `422`
     * `custom_domain_limit_reached` (one already exists), or a `hostname`
     * validation error — an invalid FQDN, a hostname on or under the platform
     * host or wildcard base, or one already claimed by another project.
     */
    public function add(string $hostname): CustomDomain
    {
        $response = $this->http->post('custom-domains', ['json' => ['hostname' => $hostname]]);

        return CustomDomain::fromArray($response['data'] ?? []);
    }

    /**
     * Re-run both verification gates now (POST /custom-domains/{id}/check).
     *
     * Throttled to ONE check per domain per 15 minutes: inside the window the
     * call is refused with `422` `check_throttled` and a `retry_after` in
     * seconds on the response body. `404` `custom_domain_not_found` for an
     * unknown or cross-project id.
     */
    public function check(int $id): CustomDomain
    {
        $response = $this->http->post('custom-domains/'.$id.'/check');

        return CustomDomain::fromArray($response['data'] ?? []);
    }

    /**
     * Remove the domain (DELETE /custom-domains/{id}, `204`).
     *
     * Public pages fall back to the platform subdomain immediately, so links in
     * already-sent mail keep working.
     */
    public function delete(int $id): void
    {
        $this->http->delete('custom-domains/'.$id);
    }
}
