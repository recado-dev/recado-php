<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\Exception\ValidationException;

/**
 * The sending-domains surface: the automated-onboarding loop (add, publish the
 * records, poll, clean up).
 */
final class SendingDomainsTest extends TestCase
{
    public function test_it_lists_domains_with_their_records_and_dmarc_recommendation(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [$this->domain()]]),
        ], $history);

        $domains = $client->sendingDomains()->list();

        $this->assertCount(1, $domains);
        $domain = $domains[0];

        $this->assertSame('mail.example.com', $domain->domain);
        // null provider = the platform's shared SES account.
        $this->assertNull($domain->provider);
        $this->assertFalse($domain->isVerified());
        $this->assertSame('bounce.mail.example.com', $domain->mailFromDomain);

        $this->assertCount(2, $domain->records);
        $this->assertSame('CNAME', $domain->records[0]->type);
        $this->assertSame('abc123._domainkey.mail', $domain->records[0]->host);
        $this->assertSame('dkim', $domain->records[0]->kind);
        // `detected` means our own resolver sees it while the provider is still
        // pending; it is never promoted to verified on our detection alone.
        $this->assertSame('detected', $domain->records[0]->state);
        $this->assertFalse($domain->records[0]->isMissing());
        // An MX priority is split out of the value into its own field.
        $this->assertSame(10, $domain->records[1]->priority);

        // Only the record our resolver cannot see yet is "your turn".
        $this->assertSame(['bounce.mail'], array_map(
            static fn ($record): ?string => $record->host,
            $domain->missingRecords(),
        ));

        // DMARC is a recommendation, so it has no provider check and no `kind`.
        $this->assertSame('_dmarc.mail', $domain->dmarc->host);
        $this->assertNull($domain->dmarc->kind);

        $this->assertSame('GET', $history[0]['request']->getMethod());
        $this->assertSame('/api/v1/sending-domains', $history[0]['request']->getUri()->getPath());
    }

    public function test_it_adds_a_domain_and_gets_the_records_to_publish_back(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->domain()]),
        ], $history);

        $domain = $client->sendingDomains()->add('mail.example.com');

        $this->assertSame(12, $domain->id);
        $this->assertNotSame([], $domain->records);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/sending-domains', $request->getUri()->getPath());
        $this->assertSame(['domain' => 'mail.example.com'], json_decode((string) $request->getBody(), true));
    }

    public function test_check_polls_the_provider_and_get_and_delete_address_one_domain(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->domain([
                'status' => 'verified',
                'verified_at' => '2026-09-12T11:00:00+00:00',
                'warmup' => ['status' => 'active', 'day_number' => 1, 'cap' => 50, 'sent_today' => 0],
            ])]),
            $this->jsonResponse(200, ['data' => $this->domain()]),
            $this->jsonResponse(204, []),
        ], $history);

        $checked = $client->sendingDomains()->check(12);
        $this->assertTrue($checked->isVerified());
        // The warm-up block is passed through as-is; skipping and resuming a
        // ramp have no API and the SDK fakes none.
        $this->assertSame(50, $checked->warmup['cap']);

        $client->sendingDomains()->get(12);
        $client->sendingDomains()->delete(12);

        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('/api/v1/sending-domains/12/check', $history[0]['request']->getUri()->getPath());
        $this->assertSame('GET', $history[1]['request']->getMethod());
        $this->assertSame('/api/v1/sending-domains/12', $history[1]['request']->getUri()->getPath());
        $this->assertSame('DELETE', $history[2]['request']->getMethod());
        $this->assertSame('/api/v1/sending-domains/12', $history[2]['request']->getUri()->getPath());
    }

    public function test_the_three_add_refusals_keep_their_machine_codes(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The active provider cannot verify domains.',
                'code' => 'verification_unsupported',
            ]),
            $this->jsonResponse(422, [
                'message' => 'This project already carries that domain.',
                'code' => 'already_added',
            ]),
            $this->jsonResponse(422, [
                'message' => 'The provider rejected the identity.',
                'code' => 'provider_error',
            ]),
        ], $history);

        foreach (['verification_unsupported', 'already_added', 'provider_error'] as $code) {
            try {
                $client->sendingDomains()->add('mail.example.com');
                $this->fail('Expected a ValidationException.');
            } catch (ValidationException $e) {
                $this->assertSame($code, $e->getErrorCode());
            }
        }
    }

    public function test_an_unknown_domain_is_not_found_and_an_unreachable_provider_is_retryable(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, [
                'message' => 'Sending domain not found.',
                'code' => 'sending_domain_not_found',
            ]),
            $this->jsonResponse(422, [
                'message' => 'The provider could not be reached.',
                'code' => 'verification_failed',
            ]),
        ], $history);

        try {
            $client->sendingDomains()->get(999);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('sending_domain_not_found', $e->getErrorCode());
        }

        try {
            $client->sendingDomains()->check(12);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            // The stored status is left untouched; the call is worth retrying.
            $this->assertSame('verification_failed', $e->getErrorCode());
        }
    }

    /**
     * The refusal is matched on the CODE, not the status: the platform is
     * unifying production-only sandbox refusals on 404 and a caller must not
     * have to care which one it got.
     */
    public function test_a_sandbox_token_is_refused_whatever_status_the_platform_uses(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, [
                'message' => 'Sending domains are not available in a sandbox.',
                'code' => 'not_available_in_sandbox',
            ]),
            $this->jsonResponse(422, [
                'message' => 'Sending domains are not available in a sandbox.',
                'code' => 'not_available_in_sandbox',
            ]),
        ], $history);

        foreach ([404, 422] as $status) {
            try {
                $client->sendingDomains()->list();
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
            'id' => 12,
            'domain' => 'mail.example.com',
            'provider' => null,
            'status' => 'pending',
            'verification_error' => null,
            'mail_from_domain' => 'bounce.mail.example.com',
            'mail_from_status' => 'pending',
            'verified_at' => null,
            'created_at' => '2026-09-12T10:04:11+00:00',
            'records' => [
                [
                    'type' => 'CNAME',
                    'host' => 'abc123._domainkey.mail',
                    'fqdn' => 'abc123._domainkey.mail.example.com',
                    'value' => 'abc123.dkim.amazonses.com',
                    'priority' => null,
                    'ttl' => '3600',
                    'kind' => 'dkim',
                    'status' => 'pending',
                    'state' => 'detected',
                ],
                [
                    'type' => 'MX',
                    'host' => 'bounce.mail',
                    'fqdn' => 'bounce.mail.example.com',
                    'value' => 'feedback-smtp.eu-west-1.amazonses.com',
                    'priority' => 10,
                    'ttl' => '3600',
                    'kind' => 'mail_from_mx',
                    'status' => 'pending',
                    'state' => 'not_found',
                ],
            ],
            'dmarc' => [
                'type' => 'TXT',
                'host' => '_dmarc.mail',
                'fqdn' => '_dmarc.mail.example.com',
                'value' => 'v=DMARC1; p=none;',
                'ttl' => '3600',
                'status' => 'not_found',
                'state' => 'not_found',
            ],
            'warmup' => null,
        ], $overrides);
    }
}
