<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\NotFoundException;

/**
 * The write half of the lists resource (PATCH/DELETE /lists/{id}).
 */
final class ListsWritesTest extends TestCase
{
    public function test_update_patches_only_the_submitted_fields(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'id' => 12,
                'name' => 'Weekly digest',
                'description' => 'Weekly product newsletter',
                'contacts_count' => 1840,
                'created_at' => '2026-05-01T10:00:00+00:00',
            ]]),
        ], $history);

        $list = $client->lists()->update(12, ['name' => 'Weekly digest']);

        $this->assertSame('Weekly digest', $list->name);
        $this->assertSame(1840, $list->contactsCount);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/lists/12', $request->getUri()->getPath());
        $this->assertSame(['name' => 'Weekly digest'], json_decode((string) $request->getBody(), true));
    }

    public function test_delete_issues_a_delete_and_returns_nothing(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(204, []),
        ], $history);

        $client->lists()->delete(12);

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/api/v1/lists/12', $request->getUri()->getPath());
    }

    public function test_a_list_outside_the_project_is_a_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'List not found.', 'code' => 'list_not_found']),
        ], $history);

        try {
            $client->lists()->update(999, ['name' => 'Nope']);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('list_not_found', $e->getErrorCode());
        }
    }
}
