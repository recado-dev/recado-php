<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Contact;
use Recado\Sdk\Dto\Paginated;

final class ContactsTest extends TestCase
{
    public function test_list_parses_paginated_contacts(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [
                    [
                        'uuid' => 'c-1',
                        'email' => 'jane@example.com',
                        'first_name' => 'Jane',
                        'last_name' => 'Doe',
                        'status' => 'subscribed',
                        'locale' => 'en',
                        'attributes' => ['plan' => 'pro'],
                        'tags' => [['id' => 1, 'name' => 'vip', 'color' => '#fff']],
                        'subscribed_at' => '2026-01-01T00:00:00Z',
                        'unsubscribed_at' => null,
                        'created_at' => '2026-01-01T00:00:00Z',
                        'updated_at' => '2026-01-01T00:00:00Z',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 25,
                    'total' => 1,
                    'last_page' => 1,
                    'from' => 1,
                    'to' => 1,
                ],
                'links' => ['first' => 'f', 'last' => 'l', 'prev' => null, 'next' => null],
            ]),
        ], $history);

        $page = $client->contacts()->list(['status' => 'subscribed', 'per_page' => 25]);

        $this->assertInstanceOf(Paginated::class, $page);
        $this->assertCount(1, $page->data);
        $this->assertInstanceOf(Contact::class, $page->data[0]);
        $this->assertSame('jane@example.com', $page->data[0]->email);
        $this->assertSame('pro', $page->data[0]->attributes['plan']);
        $this->assertCount(1, $page->data[0]->tags);
        $this->assertSame('vip', $page->data[0]->tags[0]->name);
        $this->assertSame(1, $page->meta['total']);

        $request = $history[0]['request'];
        $this->assertSame('/api/v1/contacts', $request->getUri()->getPath());
        $this->assertStringContainsString('status=subscribed', $request->getUri()->getQuery());
        $this->assertStringContainsString('per_page=25', $request->getUri()->getQuery());
    }

    public function test_get_parses_single_contact_with_tags_and_lists(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => [
                    'uuid' => 'c-1',
                    'email' => 'jane@example.com',
                    'first_name' => 'Jane',
                    'status' => 'subscribed',
                    'attributes' => [],
                    'tags' => [['id' => 1, 'name' => 'vip', 'color' => null]],
                    'lists' => [['id' => 7, 'name' => 'Newsletter']],
                ],
            ]),
        ], $history);

        $contact = $client->contacts()->get('jane@example.com');

        $this->assertSame('jane@example.com', $contact->email);
        $this->assertSame('Jane', $contact->firstName);
        $this->assertCount(1, $contact->tags);
        $this->assertSame('vip', $contact->tags[0]->name);
        $this->assertCount(1, $contact->lists);
        $this->assertSame(7, $contact->lists[0]->id);
        $this->assertSame('Newsletter', $contact->lists[0]->name);

        $request = $history[0]['request'];
        $this->assertSame('/api/v1/contacts/jane%40example.com', $request->getUri()->getPath());
    }

    public function test_tags_endpoint_returns_sorted_names(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, [
                'data' => ['email' => 'jane@example.com', 'tags' => ['alpha', 'beta']],
            ]),
        ], $history);

        $data = $client->contacts()->tags('jane@example.com', add: ['beta'], remove: ['gamma']);

        $this->assertSame(['alpha', 'beta'], $data['tags']);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/contacts/jane%40example.com/tags', $request->getUri()->getPath());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(['beta'], $body['add']);
        $this->assertSame(['gamma'], $body['remove']);
    }
}
