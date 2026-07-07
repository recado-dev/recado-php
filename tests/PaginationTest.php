<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Contact;

final class PaginationTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $contacts
     * @param array<string, mixed>             $extra
     *
     * @return array<string, mixed>
     */
    private function contactsPage(array $contacts, int $currentPage, int $lastPage, array $extra = []): array
    {
        return array_merge([
            'data' => $contacts,
            'meta' => ['current_page' => $currentPage, 'last_page' => $lastPage, 'per_page' => 2, 'total' => 5],
            'links' => ['next' => $currentPage < $lastPage ? 'next-url' : null],
        ], $extra);
    }

    /**
     * @param int|string $id
     *
     * @return array<string, mixed>
     */
    private function contact(int|string $id): array
    {
        return [
            'uuid' => 'c-'.$id,
            'email' => 'user'.$id.'@example.com',
            'status' => 'subscribed',
            'attributes' => [],
            'tags' => [],
        ];
    }

    public function test_cursor_walks_all_pages_in_order(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, $this->contactsPage([$this->contact(1), $this->contact(2)], 1, 3)),
            $this->jsonResponse(200, $this->contactsPage([$this->contact(3), $this->contact(4)], 2, 3)),
            $this->jsonResponse(200, $this->contactsPage([$this->contact(5)], 3, 3)),
        ], $history);

        $emails = [];

        foreach ($client->contacts()->cursor(['status' => 'subscribed']) as $contact) {
            $this->assertInstanceOf(Contact::class, $contact);
            $emails[] = $contact->email;
        }

        $this->assertSame([
            'user1@example.com',
            'user2@example.com',
            'user3@example.com',
            'user4@example.com',
            'user5@example.com',
        ], $emails);

        $this->assertCount(3, $history, 'Expected exactly one request per page.');

        // Each request carries the right page query param (and keeps the filter).
        foreach ([1, 2, 3] as $i => $expectedPage) {
            $query = $history[$i]['request']->getUri()->getQuery();
            $this->assertStringContainsString('page='.$expectedPage, $query);
            $this->assertStringContainsString('status=subscribed', $query);
        }
    }

    public function test_cursor_single_page_yields_items_and_stops(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, $this->contactsPage([$this->contact(1), $this->contact(2)], 1, 1)),
        ], $history);

        $contacts = iterator_to_array($client->contacts()->cursor(), false);

        $this->assertCount(2, $contacts);
        $this->assertCount(1, $history, 'A single-page result must not fetch a second page.');
    }

    public function test_cursor_empty_first_page_yields_nothing(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, $this->contactsPage([], 1, 1)),
        ], $history);

        $contacts = iterator_to_array($client->contacts()->cursor(), false);

        $this->assertSame([], $contacts);
        $this->assertCount(1, $history);
    }
}
