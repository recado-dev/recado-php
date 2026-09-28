<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

final class ListDoubleOptInTest extends TestCase
{
    public function test_create_sends_the_confirmation_settings_and_parses_them_back(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => [
                'id' => 12,
                'name' => 'Beta',
                'description' => null,
                'requires_confirmation' => true,
                'confirmation_template' => 'confirm-beta',
                'pending_count' => 3,
                'created_at' => '2026-09-28T10:00:00+00:00',
            ]]),
        ], $history);

        $list = $client->lists()->create('Beta', requiresConfirmation: true, confirmationTemplate: 'confirm-beta');

        $this->assertTrue($list->requiresConfirmation);
        $this->assertSame('confirm-beta', $list->confirmationTemplate);
        $this->assertSame(3, $list->pendingCount);

        $this->assertSame(
            ['name' => 'Beta', 'requires_confirmation' => true, 'confirmation_template' => 'confirm-beta'],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
    }

    public function test_create_without_the_new_arguments_sends_the_pre_2_7_payload(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => ['id' => 1, 'name' => 'News', 'requires_confirmation' => false]]),
        ], $history);

        $list = $client->lists()->create('News', 'Weekly');

        $this->assertFalse($list->requiresConfirmation);
        $this->assertNull($list->confirmationTemplate);
        $this->assertSame(
            ['name' => 'News', 'description' => 'Weekly'],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
    }

    public function test_update_forwards_the_confirmation_settings(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['id' => 12, 'requires_confirmation' => false, 'confirmation_template' => null]]),
        ], $history);

        $list = $client->lists()->update(12, ['requires_confirmation' => false, 'confirmation_template' => null]);

        $this->assertFalse($list->requiresConfirmation);
        $this->assertSame('PATCH', $history[0]['request']->getMethod());
        $this->assertSame(
            ['requires_confirmation' => false, 'confirmation_template' => null],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
    }

    public function test_contact_profile_separates_memberships_from_pending_requests(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'uuid' => 'c-1',
                'email' => 'ana@example.com',
                'status' => 'subscribed',
                'lists' => [
                    ['id' => 1, 'name' => 'News', 'status' => 'confirmed', 'confirmed_at' => null],
                    ['id' => 12, 'name' => 'Beta', 'status' => 'confirmed', 'confirmed_at' => '2026-09-28T10:05:00+00:00'],
                ],
                'pending_lists' => [[
                    'id' => 14,
                    'name' => 'Offers',
                    'status' => 'pending',
                    'requested_at' => '2026-09-28T10:00:00+00:00',
                    'confirmation_sent_at' => '2026-09-28T10:00:01+00:00',
                    'expires_at' => '2026-10-05T10:00:00+00:00',
                ]],
            ]]),
        ], $history);

        $contact = $client->contacts()->get('ana@example.com');

        $this->assertCount(2, $contact->lists);
        $this->assertSame('confirmed', $contact->lists[1]->status);
        $this->assertNull($contact->lists[0]->confirmedAt);
        $this->assertSame('2026-09-28T10:05:00+00:00', $contact->lists[1]->confirmedAt);
        $this->assertFalse($contact->lists[1]->isPending());

        $this->assertCount(1, $contact->pendingLists);
        $pending = $contact->pendingLists[0];
        $this->assertTrue($pending->isPending());
        $this->assertSame(14, $pending->id);
        $this->assertSame('2026-09-28T10:00:00+00:00', $pending->requestedAt);
        $this->assertSame('2026-09-28T10:00:01+00:00', $pending->confirmationSentAt);
        $this->assertSame('2026-10-05T10:00:00+00:00', $pending->expiresAt);
    }

    public function test_a_listing_contact_without_pending_lists_reports_an_empty_array(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['uuid' => 'c-1', 'email' => 'ana@example.com']]),
        ], $history);

        $this->assertSame([], $client->contacts()->get('ana@example.com')->pendingLists);
    }

    public function test_subscribe_returns_the_per_list_outcome(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'id' => 'c-1',
                'email' => 'ana@example.com',
                'status' => 'subscribed',
                'lists' => [
                    ['id' => 1, 'status' => 'confirmed', 'confirmation_email' => null],
                    ['id' => 12, 'status' => 'pending', 'confirmation_email' => 'sent'],
                ],
            ]]),
        ], $history);

        $data = $client->contacts()->subscribe(['email' => 'ana@example.com', 'lists' => [1, 12]]);

        $this->assertSame('pending', $data['lists'][1]['status']);
        $this->assertSame('sent', $data['lists'][1]['confirmation_email']);
    }
}
