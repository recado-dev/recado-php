<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\ValidationException;

/**
 * The campaign publication flags: `in_archive` (listed on the project's public
 * archive and RSS feed once sent) and `premium` (paid subscribers only).
 */
final class CampaignPublicationFlagsTest extends TestCase
{
    public function test_the_flags_are_read_back_from_every_campaign_representation(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign(['in_archive' => false, 'premium' => true])]),
        ], $history);

        $campaign = $client->campaigns()->get(7);

        $this->assertFalse($campaign->inArchive);
        $this->assertTrue($campaign->premium);
    }

    public function test_a_campaign_payload_without_the_flags_reports_them_as_null(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->campaign()]),
        ], $history);

        $campaign = $client->campaigns()->get(7);

        // Null is "not reported by this payload", which stays distinct from a
        // flag that is genuinely off.
        $this->assertNull($campaign->inArchive);
        $this->assertNull($campaign->premium);
    }

    public function test_create_and_update_pass_the_flags_through(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->campaign(['in_archive' => true, 'premium' => false])]),
            $this->jsonResponse(200, ['data' => $this->campaign(['in_archive' => false, 'premium' => false])]),
        ], $history);

        $client->campaigns()->create(['name' => 'September', 'in_archive' => true, 'premium' => false]);
        $client->campaigns()->update(7, ['in_archive' => false]);

        $created = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertTrue($created['in_archive']);
        $this->assertFalse($created['premium']);

        $updated = json_decode((string) $history[1]['request']->getBody(), true);
        $this->assertSame(['in_archive' => false], $updated);
    }

    public function test_premium_without_monetization_keeps_its_machine_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Monetization is disabled for this project.',
                'code' => 'premium_monetization_disabled',
            ]),
        ], $history);

        try {
            $client->campaigns()->update(7, ['premium' => true]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            // Turning premium OFF is always allowed; turning it on is not,
            // and nothing is written when it is refused.
            $this->assertSame('premium_monetization_disabled', $e->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function campaign(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'name' => 'September newsletter',
            'subject' => 'What is new',
            'status' => 'draft',
            'recipients_total' => 0,
            'dispatched_total' => null,
            'sent_count' => 0,
            'failed_count' => 0,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => '2026-09-01T10:00:00+00:00',
        ], $overrides);
    }
}
