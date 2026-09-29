<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\EmailVerification;
use Recado\Sdk\Exception\ValidationException;

/**
 * Real-time verification: `verify()->email()` (POST /verify).
 */
final class VerifyTest extends TestCase
{
    public function test_it_posts_the_address_and_maps_the_verdict(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'email' => 'ana@gmial.com',
                'status' => 'risky',
                'reasons' => ['domain_typo'],
                'did_you_mean' => 'ana@gmail.com',
                'disposable' => false,
                'role' => false,
                'mx_found' => true,
                'smtp' => null,
            ]]),
        ], $history);

        $result = $client->verify()->email('Ana@Gmial.com');

        $this->assertInstanceOf(EmailVerification::class, $result);
        $this->assertSame('ana@gmial.com', $result->email);
        $this->assertTrue($result->isRisky());
        $this->assertFalse($result->isInvalid());
        $this->assertTrue($result->hasReason('domain_typo'));
        $this->assertSame('ana@gmail.com', $result->didYouMean);
        $this->assertTrue($result->mxFound);
        $this->assertNull($result->smtpStatus);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/verify', $request->getUri()->getPath());
        $this->assertSame(['email' => 'Ana@Gmial.com'], json_decode((string) $request->getBody(), true));
    }

    public function test_an_undetermined_route_and_a_cached_smtp_answer_are_exposed(): void
    {
        $unknown = EmailVerification::fromArray([
            'email' => 'ana@slow.test',
            'status' => 'unknown',
            'reasons' => ['domain_unresolvable'],
            'did_you_mean' => null,
            'mx_found' => null,
            'smtp' => null,
        ]);

        $this->assertTrue($unknown->isUnknown());
        $this->assertNull($unknown->mxFound);

        $rejected = EmailVerification::fromArray([
            'email' => 'gone@example.com',
            'status' => 'invalid',
            'reasons' => ['smtp_rejected'],
            'mx_found' => true,
            'smtp' => ['status' => 'rejected', 'checked_at' => '2026-09-29T10:00:00+00:00'],
        ]);

        $this->assertTrue($rejected->isInvalid());
        $this->assertSame('rejected', $rejected->smtpStatus);
        $this->assertSame('2026-09-29T10:00:00+00:00', $rejected->smtpCheckedAt);
    }

    public function test_the_monthly_cap_surfaces_as_a_coded_validation_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Your plan quota of 1000 email verifications per month has been reached.',
                'code' => 'verification_quota_exceeded',
            ]),
        ], $history);

        try {
            $client->verify()->email('ana@example.com');
            $this->fail('A spent allowance must throw.');
        } catch (ValidationException $exception) {
            $this->assertSame('verification_quota_exceeded', $exception->getErrorCode());
        }

        // Never retried: waiting does not refill a monthly allowance.
        $this->assertCount(1, $history);
    }
}
