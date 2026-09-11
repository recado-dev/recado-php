<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\CampaignAbTest;
use Recado\Sdk\Exception\ValidationException;

/**
 * The A/B authoring contract: an A/B campaign can be created end to end
 * from the SDK, and the authored test comes back typed on every read and
 * write.
 */
final class CampaignAbTestTest extends TestCase
{
    public function test_create_sends_the_ab_pair_and_parses_the_authored_test(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->abCampaign()]),
        ], $history);

        $payload = [
            'name' => 'July product update',
            'subject' => 'What shipped in July',
            'editor' => 'markdown',
            'content' => ['source' => '# Base'],
            'lists' => [3],
            'ab_test' => [
                'enabled' => true,
                'test_fraction' => 0.2,
                'winner_metric' => 'opens',
                'test_duration_minutes' => 240,
            ],
            'variants' => [
                ['subject' => 'What shipped in July'],
                ['subject' => 'July: 11 new things', 'content' => ['source' => '# Eleven']],
            ],
        ];

        $campaign = $client->campaigns()->create($payload);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/campaigns', $request->getUri()->getPath());
        // The pair travels verbatim: the SDK adds no envelope of its own.
        $this->assertSame($payload, json_decode((string) $request->getBody(), true));

        $this->assertInstanceOf(CampaignAbTest::class, $campaign->abTest);
        $this->assertTrue($campaign->abTest->enabled);
        $this->assertFalse($campaign->abTest->locked);
        $this->assertNull($campaign->abTest->state);
        $this->assertSame(0.2, $campaign->abTest->testFraction);
        $this->assertSame('opens', $campaign->abTest->winnerMetric);
        $this->assertSame(240, $campaign->abTest->testDurationMinutes);
        $this->assertCount(2, $campaign->abTest->variants);

        [$a, $b] = $campaign->abTest->variants;

        $this->assertSame('A', $a->label);
        $this->assertSame('What shipped in July', $a->subject);
        // Null content is the "inherits the campaign body" marker.
        $this->assertNull($a->content);
        $this->assertSame('B', $b->label);
        $this->assertSame(['source' => '# Eleven'], $b->content);
    }

    public function test_update_replaces_the_variant_set(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->abCampaign()]),
        ], $history);

        $client->campaigns()->update(42, [
            'variants' => [['subject' => 'A'], ['subject' => 'B']],
        ]);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42', $request->getUri()->getPath());
        $this->assertSame(
            ['variants' => [['subject' => 'A'], ['subject' => 'B']]],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function test_a_locked_test_is_visible_before_the_write_and_refused_after_it(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->abCampaign([
                'ab_test' => [...$this->abTest(), 'state' => 'testing', 'locked' => true],
            ])]),
            $this->jsonResponse(422, [
                'message' => 'The A/B test is already running; its variants can no longer be changed.',
                'code' => 'ab_test_locked',
            ]),
        ], $history);

        $campaign = $client->campaigns()->get(42);

        $this->assertTrue($campaign->abTest?->locked);
        $this->assertSame('testing', $campaign->abTest?->state);

        try {
            $client->campaigns()->update(42, ['variants' => [['subject' => 'Sneaky']]]);
            $this->fail('a locked A/B test must refuse the write');
        } catch (ValidationException $e) {
            $this->assertSame('ab_test_locked', $e->getErrorCode());
        }
    }

    public function test_a_campaign_without_an_ab_block_has_a_null_test(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['id' => 42, 'name' => 'Plain', 'status' => 'draft']]),
        ], $history);

        $this->assertNull($client->campaigns()->get(42)->abTest);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function abCampaign(array $overrides = []): array
    {
        return array_merge([
            'id' => 42,
            'name' => 'July product update',
            'subject' => 'What shipped in July',
            'status' => 'draft',
            'recipients_total' => 0,
            'dispatched_total' => null,
            'sent_count' => 0,
            'failed_count' => 0,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => '2026-07-01T12:00:00+00:00',
            'ab_test' => $this->abTest(),
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function abTest(): array
    {
        return [
            'enabled' => true,
            'state' => null,
            'locked' => false,
            'test_fraction' => 0.2,
            'winner_metric' => 'opens',
            'test_duration_minutes' => 240,
            'resolve_due_at' => null,
            'variants' => [
                [
                    'id' => 5, 'label' => 'A', 'subject' => 'What shipped in July',
                    'preheader' => null, 'from_name' => null, 'from_email' => null,
                    'content' => null, 'is_winner' => false,
                ],
                [
                    'id' => 6, 'label' => 'B', 'subject' => 'July: 11 new things',
                    'preheader' => null, 'from_name' => null, 'from_email' => null,
                    'content' => ['source' => '# Eleven'], 'is_winner' => false,
                ],
            ],
        ];
    }
}
