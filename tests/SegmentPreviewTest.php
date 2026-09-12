<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\ValidationException;

/**
 * POST /segments/preview — dry-run a conditions tree without writing a row.
 */
final class SegmentPreviewTest extends TestCase
{
    public function test_it_counts_and_samples_without_creating_a_segment(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'contacts_count' => 320,
                'sample' => [[
                    'uuid' => '0d0b6a5e-4a6f-4a3c-9b41-2a9d8f2e2a11',
                    'email' => 'ada@example.com',
                    'first_name' => 'Ada',
                    'status' => 'subscribed',
                    'verification_status' => 'risky',
                    'verification_reasons' => [
                        ['reason' => 'domain_typo', 'suggestion' => 'gmail.com'],
                    ],
                    'verified_at' => '2026-06-09T14:00:00+00:00',
                    'tags' => [['id' => 3, 'name' => 'vip']],
                ]],
            ]]),
        ], $history);

        $conditions = [
            'match' => 'all',
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
                ['field' => 'tags', 'operator' => 'has', 'value' => 'vip'],
            ],
        ];

        $preview = $client->segments()->preview($conditions, sampleSize: 2);

        $this->assertSame(320, $preview->contactsCount);
        $this->assertCount(1, $preview->sample);
        $this->assertSame('ada@example.com', $preview->sample[0]->email);
        // The sample carries the same contact shape the listing returns,
        // verification verdict included.
        $this->assertSame('risky', $preview->sample[0]->verificationStatus);
        $this->assertSame('gmail.com', $preview->sample[0]->verificationReasons[0]['suggestion']);
        $this->assertFalse($preview->sample[0]->isInvalidEmail());

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/segments/preview', $request->getUri()->getPath());
        $this->assertSame(
            ['conditions' => $conditions, 'sample_size' => 2],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function test_a_sample_size_of_zero_asks_for_the_count_only(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['contacts_count' => 320, 'sample' => []]]),
        ], $history);

        $preview = $client->segments()->preview(['match' => 'all', 'conditions' => []], sampleSize: 0);

        $this->assertSame(320, $preview->contactsCount);
        $this->assertSame([], $preview->sample);
        $this->assertSame(
            0,
            json_decode((string) $history[0]['request']->getBody(), true)['sample_size'],
        );
    }

    public function test_validation_is_the_same_as_create_so_a_bad_field_is_refused(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The given data was invalid.',
                'errors' => ['conditions' => ['The conditions field is invalid.']],
            ]),
        ], $history);

        try {
            $client->segments()->preview(['match' => 'all', 'conditions' => [
                ['field' => 'email); DROP TABLE--', 'operator' => 'equals', 'value' => 'x'],
            ]]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conditions', $e->errors());
        }
    }
}
