<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\Tag;
use Recado\Sdk\Exception\NotFoundException;

/**
 * The tags resource: the listing plus the create-or-find / update / delete
 * surface, including the preference-center trio.
 */
final class TagsTest extends TestCase
{
    public function test_list_parses_the_preference_center_fields(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                $this->tag(),
                $this->tag([
                    'id' => 3,
                    'name' => 'customer',
                    'color' => null,
                    'is_public' => false,
                    'public_label' => null,
                    'public_description' => null,
                ]),
            ]]),
        ], $history);

        $tags = $client->tags()->list();

        $this->assertCount(2, $tags);
        $this->assertContainsOnlyInstancesOf(Tag::class, $tags);
        $this->assertTrue($tags[0]->isPublic);
        $this->assertSame('Insider news', $tags[0]->publicLabel);
        $this->assertSame('Occasional early access.', $tags[0]->publicDescription);
        $this->assertSame('2026-05-04T09:12:00+00:00', $tags[0]->createdAt);

        // A private tag carries the flag as false, and its labels as null —
        // never absent, so a caller can tell "off" from "not reported".
        $this->assertFalse($tags[1]->isPublic);
        $this->assertNull($tags[1]->publicLabel);

        $this->assertSame('GET', $history[0]['request']->getMethod());
        $this->assertSame('/api/v1/tags', $history[0]['request']->getUri()->getPath());
    }

    public function test_create_sends_the_name_last_so_it_can_never_be_overridden(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->tag()]),
        ], $history);

        $tag = $client->tags()->create('vip', [
            'name' => 'ignored',
            'color' => '#16a34a',
            'is_public' => true,
            'public_label' => 'Insider news',
        ]);

        $this->assertSame(7, $tag->id);
        $this->assertSame('vip', $tag->name);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/tags', $request->getUri()->getPath());

        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('vip', $payload['name']);
        $this->assertSame('#16a34a', $payload['color']);
        $this->assertTrue($payload['is_public']);
    }

    public function test_create_is_create_or_find_and_a_match_comes_back_unchanged(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            // 200 (not 201) is the API's way of saying an existing tag matched
            // case-insensitively; it comes back with the FIRST casing.
            $this->jsonResponse(200, ['data' => $this->tag()]),
        ], $history);

        $tag = $client->tags()->create('VIP');

        $this->assertSame('vip', $tag->name);
    }

    public function test_update_patches_the_tag(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->tag(['public_label' => 'Insiders'])]),
        ], $history);

        $tag = $client->tags()->update(7, ['public_label' => 'Insiders']);

        $this->assertSame('Insiders', $tag->publicLabel);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/tags/7', $request->getUri()->getPath());
        $this->assertSame(['public_label' => 'Insiders'], json_decode((string) $request->getBody(), true));
    }

    public function test_delete_issues_a_delete(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(204, []),
        ], $history);

        $client->tags()->delete(7);

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/api/v1/tags/7', $request->getUri()->getPath());
    }

    public function test_a_tag_outside_the_project_is_a_not_found(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(404, ['message' => 'Tag not found.', 'code' => 'tag_not_found']),
        ], $history);

        try {
            $client->tags()->delete(999);
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('tag_not_found', $e->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function tag(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'name' => 'vip',
            'color' => '#16a34a',
            'is_public' => true,
            'public_label' => 'Insider news',
            'public_description' => 'Occasional early access.',
            'contacts_count' => 96,
            'created_at' => '2026-05-04T09:12:00+00:00',
        ], $overrides);
    }
}
