<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\Exception\ValidationException;

/**
 * The custom-domains surface: the project's own public hostname.
 */
final class CustomDomainsTest extends TestCase
{
    public function test_current_collapses_the_zero_or_one_collection(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [$this->domain()]]),
            $this->jsonResponse(200, ['data' => []]),
        ], $history);

        $domain = $client->customDomains()->current();
        $this->assertSame('news.acme.com', $domain->hostname);
        $this->assertFalse($domain->isVerified());
        $this->assertSame('CNAME', $domain->record->type);
        $this->assertSame('acme.recado.dev', $domain->record->value);

        // One domain per project: an empty collection is "none", not an error.
        $this->assertNull($client->customDomains()->current());

        $this->assertSame('/api/v1/custom-domains', $history[0]['request']->getUri()->getPath());
    }

    public function test_add_sends_the_hostname_and_check_reports_the_tls_gate_separately(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->domain()]),
            $this->jsonResponse(200, ['data' => $this->domain([
                'tls_pending' => true,
                'verification_error' => 'tls_pending',
                'consecutive_failures' => 1,
                'last_checked_at' => '2026-09-12T09:05:00+00:00',
            ])]),
        ], $history);

        $client->customDomains()->add('news.acme.com');
        $checked = $client->customDomains()->check(4);

        // "DNS ok, no certificate yet" is its own state, distinct from a
        // missing CNAME.
        $this->assertTrue($checked->tlsPending);
        $this->assertFalse($checked->tlsFailed);
        $this->assertSame('tls_pending', $checked->verificationError);
        $this->assertSame(1, $checked->consecutiveFailures);

        $this->assertSame(
            ['hostname' => 'news.acme.com'],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
        $this->assertSame('POST', $history[1]['request']->getMethod());
        $this->assertSame('/api/v1/custom-domains/4/check', $history[1]['request']->getUri()->getPath());
    }

    public function test_a_failed_certificate_keeps_its_machine_reason(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->domain([
                'status' => 'failed',
                'tls_failed' => true,
                'tls_error' => 'rate_limited',
                'verification_error' => 'tls_failed',
                'consecutive_failures' => 3,
            ])]),
        ], $history);

        $domain = $client->customDomains()->check(4);

        // Machine codes, never a localized sentence: an integration branches on
        // them.
        $this->assertSame('rate_limited', $domain->tlsError);
        $this->assertSame('tls_failed', $domain->verificationError);
        $this->assertFalse($domain->isVerified());
    }

    public function test_adding_is_plan_gated_and_limited_to_one_domain(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Your plan does not include custom domains.',
                'code' => 'custom_domains_not_entitled',
            ]),
            $this->jsonResponse(422, [
                'message' => 'This project already has a custom domain.',
                'code' => 'custom_domain_limit_reached',
            ]),
        ], $history);

        foreach (['custom_domains_not_entitled', 'custom_domain_limit_reached'] as $code) {
            try {
                $client->customDomains()->add('news.acme.com');
                $this->fail('Expected a ValidationException.');
            } catch (ValidationException $e) {
                $this->assertSame($code, $e->getErrorCode());
            }
        }
    }

    public function test_checking_too_often_is_throttled_with_a_retry_after(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'This domain was checked less than 15 minutes ago.',
                'code' => 'check_throttled',
                'retry_after' => 873,
            ]),
            $this->jsonResponse(404, [
                'message' => 'Custom domain not found.',
                'code' => 'custom_domain_not_found',
            ]),
        ], $history);

        try {
            $client->customDomains()->check(4);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('check_throttled', $e->getErrorCode());
            // `retry_after` lives on the body, not on a Retry-After header.
            $this->assertSame(873, $e->getBody()['retry_after']);
        }

        try {
            $client->customDomains()->delete(999);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('custom_domain_not_found', $e->getErrorCode());
        }
    }

    public function test_a_sandbox_token_is_refused_whatever_status_the_platform_uses(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Custom domains are not available in a sandbox.',
                'code' => 'not_available_in_sandbox',
            ]),
            $this->jsonResponse(404, [
                'message' => 'Custom domains are not available in a sandbox.',
                'code' => 'not_available_in_sandbox',
            ]),
        ], $history);

        foreach ([422, 404] as $status) {
            try {
                $client->customDomains()->list();
                $this->fail('Expected a RecadoException.');
            } catch (RecadoException $e) {
                $this->assertTrue($e->isNotAvailableInSandbox());
                $this->assertSame($status, $e->getStatus());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function domain(array $overrides = []): array
    {
        return array_merge([
            'id' => 4,
            'hostname' => 'news.acme.com',
            'status' => 'pending',
            'tls_pending' => false,
            'tls_failed' => false,
            'tls_error' => null,
            'verification_error' => null,
            'consecutive_failures' => 0,
            'last_checked_at' => null,
            'verified_at' => null,
            'created_at' => '2026-09-12T09:00:00+00:00',
            'record' => ['type' => 'CNAME', 'name' => 'news.acme.com', 'value' => 'acme.recado.dev'],
        ], $overrides);
    }
}
