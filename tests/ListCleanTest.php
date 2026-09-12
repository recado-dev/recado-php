<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\ValidationException;

/**
 * POST /lists/{id}/clean — membership only, inline or queued.
 */
final class ListCleanTest extends TestCase
{
    public function test_it_cleans_inline_and_defaults_to_every_unmailable_status(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'list_id' => 12,
                'statuses' => ['unsubscribed', 'bounced', 'complained'],
                'invalid_emails' => false,
                'matching' => 31,
                'removed' => 31,
                'queued' => false,
            ]]),
        ], $history);

        $result = $client->lists()->clean(12);

        $this->assertFalse($result->queued);
        $this->assertSame(31, $result->removed);
        $this->assertSame(['unsubscribed', 'bounced', 'complained'], $result->statuses);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/lists/12/clean', $request->getUri()->getPath());
        // An omitted `statuses` is not the same request as an empty array, so
        // the key is left out entirely rather than sent as [].
        $this->assertSame(['invalid_emails' => false], json_decode((string) $request->getBody(), true));
    }

    public function test_it_narrows_the_statuses_and_can_add_invalid_addresses(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'list_id' => 12, 'statuses' => ['bounced'], 'invalid_emails' => true,
                'matching' => 4, 'removed' => 4, 'queued' => false,
            ]]),
        ], $history);

        $client->lists()->clean(12, ['bounced'], invalidEmails: true);

        $this->assertSame(
            ['invalid_emails' => true, 'statuses' => ['bounced']],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
    }

    public function test_a_large_list_is_queued_and_reports_a_null_removed_count(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'list_id' => 12,
                'statuses' => ['unsubscribed', 'bounced', 'complained'],
                'invalid_emails' => false,
                'matching' => 42000,
                'removed' => null,
                'queued' => true,
            ]]),
        ], $history);

        $result = $client->lists()->clean(12);

        // Above the inline threshold the work is queued: `removed` is genuinely
        // unknown at that point, never a 0.
        $this->assertTrue($result->queued);
        $this->assertNull($result->removed);
        $this->assertSame(42000, $result->matching);
    }

    public function test_cleaning_nothing_is_refused_and_a_foreign_list_is_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The given data was invalid.',
                'errors' => ['statuses' => ['Select at least one thing to clean.']],
            ]),
            $this->jsonResponse(404, ['message' => 'List not found.', 'code' => 'list_not_found']),
        ], $history);

        try {
            // An explicitly EMPTY array says "clean nothing by status"; with
            // invalid_emails off that is refused, not widened back to default.
            $client->lists()->clean(12, []);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('statuses', $e->errors());
        }

        $this->assertSame(
            ['invalid_emails' => false, 'statuses' => []],
            json_decode((string) $history[0]['request']->getBody(), true),
        );

        try {
            $client->lists()->clean(999);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('list_not_found', $e->getErrorCode());
        }
    }
}
