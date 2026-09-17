<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Dto\CampaignLocaleVariant;

/**
 * The locale-translation authoring contract: a translated campaign can be
 * created and edited end to end from the SDK, and the authored translations
 * come back typed on every read and write — the campaign-scope list and the
 * per-A/B-variant ones alike.
 */
final class CampaignLocaleVariantsTest extends TestCase
{
    public function test_create_sends_the_translations_and_parses_them_back(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->translatedCampaign()]),
        ], $history);

        $payload = [
            'name' => 'July product update',
            'subject' => 'What shipped in July',
            'editor' => 'markdown',
            'content' => ['source' => '# Base'],
            'lists' => [3],
            'locale_variants' => [
                ['locale' => 'es', 'subject' => 'Lo que lanzamos en julio', 'content' => ['source' => '# Hola']],
                ['locale' => 'fr', 'subject' => 'Nouveautes de juillet'],
            ],
        ];

        $campaign = $client->campaigns()->create($payload);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/campaigns', $request->getUri()->getPath());
        // The list travels verbatim: the SDK adds no envelope of its own.
        $this->assertSame($payload, json_decode((string) $request->getBody(), true));

        $this->assertCount(2, $campaign->localeVariants);
        $this->assertContainsOnlyInstancesOf(CampaignLocaleVariant::class, $campaign->localeVariants);

        [$es, $fr] = $campaign->localeVariants;

        $this->assertSame(9, $es->id);
        $this->assertSame('es', $es->locale);
        $this->assertSame('Lo que lanzamos en julio', $es->subject);
        $this->assertNull($es->preheader);
        $this->assertSame(['source' => '# Hola'], $es->content);

        $this->assertSame('fr', $fr->locale);
        $this->assertSame('Nouveautes de juillet', $fr->subject);
        // Null content is the "inherits the campaign body" marker.
        $this->assertNull($fr->content);
    }

    public function test_create_sends_the_translations_nested_inside_variants(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(201, ['data' => $this->translatedCampaign()]),
        ], $history);

        $payload = [
            'name' => 'July product update',
            'subject' => 'What shipped in July',
            'editor' => 'markdown',
            'content' => ['source' => '# Base'],
            'ab_test' => ['enabled' => true],
            'variants' => [
                [
                    'subject' => 'What shipped in July',
                    'locale_variants' => [['locale' => 'es', 'subject' => 'Lo que lanzamos en julio']],
                ],
                [
                    'subject' => 'July: 11 new things',
                    'locale_variants' => [['locale' => 'es', 'subject' => 'Julio: 11 novedades']],
                ],
            ],
            'locale_variants' => [['locale' => 'es', 'subject' => 'Base en espanol']],
        ];

        $client->campaigns()->create($payload);

        $this->assertSame($payload, json_decode((string) $history[0]['request']->getBody(), true));
    }

    public function test_update_replaces_the_translation_set(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->translatedCampaign()]),
        ], $history);

        $client->campaigns()->update(42, ['locale_variants' => []]);

        $request = $history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/api/v1/campaigns/42', $request->getUri()->getPath());
        // An explicit empty list is how every translation is removed; it must
        // survive the round trip rather than being dropped as "nothing to send".
        $this->assertSame(['locale_variants' => []], json_decode((string) $request->getBody(), true));
    }

    public function test_the_per_variant_translations_are_paired_onto_the_ab_variants(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => $this->translatedCampaign()]),
        ], $history);

        $campaign = $client->campaigns()->get(42);

        $this->assertNotNull($campaign->abTest);
        $this->assertCount(2, $campaign->abTest->variants);

        [$a, $b] = $campaign->abTest->variants;

        $this->assertSame('A', $a->label);
        $this->assertCount(1, $a->localeVariants);
        $this->assertSame('es', $a->localeVariants[0]->locale);
        $this->assertSame('Lo que lanzamos en julio', $a->localeVariants[0]->subject);

        $this->assertSame('B', $b->label);
        $this->assertCount(1, $b->localeVariants);
        $this->assertSame('Julio: 11 novedades', $b->localeVariants[0]->subject);
    }

    public function test_a_campaign_without_translations_has_empty_lists(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'id' => 42,
                'name' => 'Plain',
                'status' => 'draft',
                'ab_test' => [
                    'enabled' => true,
                    'locked' => false,
                    'variants' => [
                        ['id' => 5, 'label' => 'A', 'subject' => 'A', 'is_winner' => false],
                    ],
                ],
            ]]),
        ], $history);

        $campaign = $client->campaigns()->get(42);

        $this->assertSame([], $campaign->localeVariants);
        $this->assertSame([], $campaign->abTest?->variants[0]->localeVariants);
    }

    public function test_preview_and_test_send_carry_a_locale(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => ['subject' => 'Lo que lanzamos en julio', 'html' => '<p>Hola</p>']]),
            $this->jsonResponse(202, ['data' => ['sent_to' => ['dev@example.com']]]),
        ], $history);

        $client->campaigns()->preview(42, variant: 6, locale: 'es');
        $client->campaigns()->testSend(42, ['dev@example.com'], variant: 6, locale: 'es');

        $this->assertSame(
            ['variant' => 6, 'locale' => 'es'],
            json_decode((string) $history[0]['request']->getBody(), true),
        );
        $this->assertSame(
            ['emails' => ['dev@example.com'], 'variant' => 6, 'locale' => 'es'],
            json_decode((string) $history[1]['request']->getBody(), true),
        );
    }

    /**
     * A campaign payload shaped exactly as the API returns it: the
     * campaign-scope translations under `locale_variants`, and the per-variant
     * ones under `locales.variants` — a sibling of `ab_test`, not nested in it.
     *
     * @return array<string, mixed>
     */
    private function translatedCampaign(): array
    {
        return [
            'id' => 42,
            'name' => 'July product update',
            'subject' => 'What shipped in July',
            'status' => 'draft',
            'created_at' => '2026-07-01T12:00:00+00:00',
            'ab_test' => [
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
                        'content' => null, 'is_winner' => false,
                    ],
                ],
            ],
            'locale_variants' => [
                [
                    'id' => 9, 'locale' => 'es', 'subject' => 'Lo que lanzamos en julio',
                    'preheader' => null, 'content' => ['source' => '# Hola'],
                ],
                [
                    'id' => 10, 'locale' => 'fr', 'subject' => 'Nouveautes de juillet',
                    'preheader' => null, 'content' => null,
                ],
            ],
            'locales' => [
                'locale_variants' => [
                    [
                        'id' => 9, 'locale' => 'es', 'subject' => 'Lo que lanzamos en julio',
                        'preheader' => null, 'content' => ['source' => '# Hola'],
                    ],
                    [
                        'id' => 10, 'locale' => 'fr', 'subject' => 'Nouveautes de juillet',
                        'preheader' => null, 'content' => null,
                    ],
                ],
                'variants' => [
                    [
                        'id' => 5, 'label' => 'A', 'locale_variants' => [
                            [
                                'id' => 11, 'locale' => 'es', 'subject' => 'Lo que lanzamos en julio',
                                'preheader' => null, 'content' => null,
                            ],
                        ],
                    ],
                    [
                        'id' => 6, 'label' => 'B', 'locale_variants' => [
                            [
                                'id' => 12, 'locale' => 'es', 'subject' => 'Julio: 11 novedades',
                                'preheader' => null, 'content' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
