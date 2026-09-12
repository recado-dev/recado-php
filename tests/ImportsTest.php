<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Import;
use Recado\Sdk\Exception\NotFoundException;
use Recado\Sdk\Exception\ValidationException;

/**
 * The imports resource: bulk contact imports, the only API surface that
 * creates contacts in bulk WITH their consent state.
 */
final class ImportsTest extends TestCase
{
    public function test_create_posts_the_rows_with_the_envelope_options(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => $this->import()]),
        ], $history);

        $import = $client->imports()->create([
            ['email' => 'ada@example.com', 'first_name' => 'Ada', 'tags' => ['vip']],
            ['email' => 'grace@example.com', 'status' => 'unsubscribed', 'unsubscribed_at' => '2026-01-15T09:30:00Z'],
        ], [
            'lists' => [12],
            'tags' => ['migration-2026'],
            'skip_invalid_emails' => true,
        ]);

        // The create call is asynchronous: it answers with a pending run, not
        // with the imported contacts.
        $this->assertSame('pending', $import->status);
        $this->assertFalse($import->isFinished());
        $this->assertSame([12], $import->lists);
        $this->assertSame(['migration-2026'], $import->tags);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/imports', $request->getUri()->getPath());

        $payload = json_decode((string) $request->getBody(), true);
        $this->assertCount(2, $payload['rows']);
        $this->assertSame('ada@example.com', $payload['rows'][0]['email']);
        $this->assertSame([12], $payload['lists']);
        $this->assertTrue($payload['skip_invalid_emails']);
    }

    public function test_create_reindexes_the_rows_so_a_filtered_array_stays_a_json_list(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => $this->import()]),
        ], $history);

        $rows = [
            0 => ['email' => 'ada@example.com'],
            2 => ['email' => 'grace@example.com'],
        ];

        $client->imports()->create($rows);

        // Without the reindex a gapped array would serialize as a JSON object
        // and the API would reject the whole request.
        $payload = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame([['email' => 'ada@example.com'], ['email' => 'grace@example.com']], $payload['rows']);
    }

    public function test_get_reports_the_progress_counters_and_the_warnings(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->import([
                'status' => 'completed',
                'processed_rows' => 2,
                'created_rows' => 1,
                'updated_rows' => 1,
                'subscribed_rows' => 1,
                'unsubscribed_rows' => 1,
                'invalid_status_rows' => 1,
            ])]),
        ], $history);

        $import = $client->imports()->get(41);

        $this->assertTrue($import->isFinished());
        $this->assertSame(2, $import->processedRows);
        // A warning, not a failure: the row imported as subscribed but its
        // status value was not recognised.
        $this->assertSame(1, $import->invalidStatusRows);
        $this->assertNull($import->error);

        $this->assertSame('/api/v1/imports/41', $history[0]['request']->getUri()->getPath());
    }

    public function test_list_is_paginated(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [$this->import()],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1],
                'links' => ['next' => null],
            ]),
        ], $history);

        $page = $client->imports()->list(['per_page' => 25]);

        $this->assertContainsOnlyInstancesOf(Import::class, $page->data);
        $this->assertSame('/api/v1/imports', $history[0]['request']->getUri()->getPath());
    }

    public function test_an_over_quota_request_queues_nothing_and_keeps_its_code(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The import does not fit your plan.',
                'code' => 'quota_exceeded',
            ]),
        ], $history);

        try {
            $client->imports()->create([['email' => 'ada@example.com']]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('quota_exceeded', $e->getErrorCode());
        }
    }

    public function test_an_import_of_another_project_is_a_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Import not found.', 'code' => 'import_not_found']),
        ], $history);

        try {
            $client->imports()->get(999);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('import_not_found', $e->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function import(array $overrides = []): array
    {
        return array_merge([
            'id' => 41,
            'status' => 'pending',
            'source' => 'api.json',
            'lists' => [12],
            'tags' => ['migration-2026'],
            'skip_invalid_emails' => true,
            'skip_risky_emails' => false,
            'processed_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'subscribed_rows' => 0,
            'unsubscribed_rows' => 0,
            'invalid_status_rows' => 0,
            'invalid_email_rows' => 0,
            'risky_email_rows' => 0,
            'skipped_rows' => 0,
            'error' => null,
            'created_at' => '2026-09-12T10:00:00+00:00',
            'updated_at' => '2026-09-12T10:00:00+00:00',
        ], $overrides);
    }
}
