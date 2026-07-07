<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\AuthenticationException;
use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\RateLimitException;
use Recado\Sdk\Exception\ValidationException;

final class ExceptionMappingTest extends TestCase
{
    public function test_422_suppressed_maps_to_validation_exception_with_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The recipient is suppressed.',
                'code' => 'recipient_suppressed',
            ]),
        ], $history);

        try {
            $client->send()->email(['to' => 'bad@example.com', 'template' => 'welcome']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('recipient_suppressed', $e->getErrorCode());
            $this->assertSame(422, $e->getStatus());
            $this->assertSame('The recipient is suppressed.', $e->getMessage());
            $this->assertSame([], $e->errors());
            $this->assertSame('recipient_suppressed', $e->getBody()['code']);
        }
    }

    public function test_422_validation_exposes_errors_map(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The given data was invalid.',
                'errors' => [
                    'to' => ['The to field is required.'],
                    'messages.2.to' => ['The messages.2.to field is required.'],
                ],
            ]),
        ], $history);

        try {
            $client->send()->email(['template' => 'welcome']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertNull($e->getErrorCode());
            $this->assertArrayHasKey('to', $e->errors());
            $this->assertSame(['The to field is required.'], $e->errors()['to']);
            $this->assertArrayHasKey('messages.2.to', $e->errors());
        }
    }

    public function test_404_maps_to_not_found_exception_with_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, [
                'message' => 'Contact not found.',
                'code' => 'contact_not_found',
            ]),
        ], $history);

        try {
            $client->contacts()->get('missing@example.com');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('contact_not_found', $e->getErrorCode());
            $this->assertSame(404, $e->getStatus());
        }
    }

    public function test_401_maps_to_authentication_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(401, ['message' => 'Unauthenticated.']),
        ], $history);

        try {
            $client->contacts()->get('jane@example.com');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->getStatus());
            $this->assertSame('Unauthenticated.', $e->getMessage());
        }
    }

    public function test_429_maps_to_rate_limit_exception_with_retry_after(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(429, ['message' => 'Too Many Attempts.'], ['Retry-After' => '37']),
        ], $history);

        try {
            $client->send()->email(['to' => 'jane@example.com', 'template' => 'welcome']);
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getStatus());
            $this->assertSame(37, $e->retryAfter());
        }
    }
}
