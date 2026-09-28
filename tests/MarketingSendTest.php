<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Recado\Sdk\Exception\MarketingSendRefusedException;
use Recado\Sdk\Exception\ValidationException;

final class MarketingSendTest extends TestCase
{
    public function test_marketing_and_category_are_forwarded_verbatim(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'm-1', 'status' => 'queued']]),
        ], $history);

        $client->send()->email([
            'to' => 'ana@example.com',
            'template' => 'weekly-offer',
            'marketing' => true,
            'category' => 'Offers',
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertTrue($body['marketing']);
        $this->assertSame('Offers', $body['category']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function marketingCodes(): array
    {
        return array_combine(
            MarketingSendRefusedException::CODES,
            array_map(static fn (string $code): array => [$code], MarketingSendRefusedException::CODES),
        );
    }

    #[DataProvider('marketingCodes')]
    public function test_marketing_refusals_throw_the_typed_exception(string $code): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'Refused.', 'code' => $code]),
        ], $history);

        try {
            $client->send()->email(['to' => 'ana@example.com', 'template' => 'weekly-offer', 'marketing' => true]);
            $this->fail('Expected a MarketingSendRefusedException.');
        } catch (MarketingSendRefusedException $e) {
            $this->assertInstanceOf(ValidationException::class, $e);
            $this->assertSame($code, $e->getErrorCode());
            $this->assertSame(422, $e->getStatus());
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sharedCodes(): array
    {
        return [
            'recipient_suppressed' => ['recipient_suppressed'],
            'sending_provider_required' => ['sending_provider_required'],
            'sending_domain_not_verified' => ['sending_domain_not_verified'],
        ];
    }

    #[DataProvider('sharedCodes')]
    public function test_shared_refusals_stay_plain_validation_exceptions(string $code): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'Refused.', 'code' => $code]),
        ], $history);

        try {
            $client->send()->email(['to' => 'ana@example.com', 'template' => 'weekly-offer', 'marketing' => true]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertNotInstanceOf(MarketingSendRefusedException::class, $e);
            $this->assertSame($code, $e->getErrorCode());
        }
    }

    public function test_contact_not_found_on_other_endpoints_is_not_a_marketing_refusal(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'No contact.', 'code' => 'contact_not_found']),
        ], $history);

        try {
            $client->messages()->resend('m-1');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertNotInstanceOf(MarketingSendRefusedException::class, $e);
        }
    }

    public function test_batch_items_carry_marketing_codes_and_suppressed_count(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'messages' => [
                    ['index' => 0, 'status' => 'queued', 'id' => 'm-1'],
                    ['index' => 1, 'status' => 'failed', 'code' => 'recipient_not_subscribed', 'error' => 'Unsubscribed.'],
                    ['index' => 2, 'status' => 'suppressed', 'code' => 'recipient_suppressed'],
                ],
                'queued' => 1,
                'suppressed' => 1,
                'failed' => 1,
            ]]),
        ], $history);

        $result = $client->send()->batch([
            ['to' => 'ana@example.com', 'template' => 'offer', 'marketing' => true, 'category' => 'Offers'],
            ['to' => 'bob@example.com', 'template' => 'offer', 'marketing' => true],
            ['to' => 'eve@example.com', 'template' => 'offer', 'marketing' => true],
        ]);

        $this->assertSame('recipient_not_subscribed', $result->messages[1]->code);
        $this->assertSame(1, $result->failed);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertTrue($body['messages'][0]['marketing']);
        $this->assertSame('Offers', $body['messages'][0]['category']);
    }

    public function test_message_exposes_the_marketing_flag_and_category(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'uuid' => 'm-1',
                'automation_id' => null,
                'is_marketing' => true,
                'category' => ['id' => 12, 'name' => 'Offers'],
            ]]),
            $this->jsonResponse(200, ['data' => [
                'uuid' => 'm-2',
                'is_marketing' => false,
                'category' => null,
            ]]),
        ], $history);

        $marketing = $client->messages()->get('m-1');
        $transactional = $client->messages()->get('m-2');

        $this->assertTrue($marketing->isMarketing);
        $this->assertNotNull($marketing->category);
        $this->assertSame(12, $marketing->category->id);
        $this->assertSame('Offers', $marketing->category->name);
        $this->assertFalse($transactional->isMarketing);
        $this->assertNull($transactional->category);
    }
}
