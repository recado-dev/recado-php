<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A campaign (a newsletter / broadcast).
 *
 * `stats` is populated by the single-campaign endpoint (GET /campaigns/{id})
 * and by a list requested with `include=stats`; it is null otherwise.
 * `topLinks` and `variants` are null unless the detail call asked for them
 * (`include=top_links,variants`) — an A/B-less campaign asked for `variants`
 * gets an empty array, which is how "requested but empty" stays distinct from
 * "not requested".
 *
 * `inArchive` and `premium` are the publication flags: whether the campaign is
 * listed on the project's public archive and RSS feed once sent, and whether it
 * only goes to paid subscribers. Both are editable on drafts only, and
 * `premium: true` is rejected (`premium_monetization_disabled`) while the
 * project's monetization is off.
 *
 * `abTest` is the AUTHORED test (configuration + the variants as written) and
 * needs no include: the detail, create and update endpoints all return it.
 * It is null on list rows, which never carry it.
 *
 * `localeVariants` is the AUTHORED set of translations of the campaign base,
 * returned — like `abTest` — by the detail, create and update endpoints and
 * never by the listing. It is an empty array when the campaign has none AND
 * when the payload never carried the key, because a client that reads a list
 * row has no translations to show either way. Each A/B variant carries its own
 * under `$abTest->variants[*]->localeVariants`: the API returns those under
 * `locales.variants[]`, and this DTO pairs them back onto the variant by id.
 */
final readonly class Campaign
{
    /**
     * @param  array<int, CampaignTopLink>|null  $topLinks
     * @param  array<int, CampaignVariant>|null  $variants
     * @param  array<int, CampaignLocaleVariant>  $localeVariants  The campaign-scope
     *                                                             translations.
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $subject,
        public ?string $status,
        public ?int $recipientsTotal,
        public ?int $dispatchedTotal,
        public ?int $sentCount,
        public ?int $failedCount,
        public ?string $scheduledAt,
        public ?string $startedAt,
        public ?string $finishedAt,
        public ?string $createdAt,
        public ?CampaignStats $stats,
        public ?array $topLinks = null,
        public ?array $variants = null,
        public ?CampaignAbTest $abTest = null,
        public ?bool $inArchive = null,
        public ?bool $premium = null,
        public array $localeVariants = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $stats = isset($data['stats']) && is_array($data['stats'])
            ? CampaignStats::fromArray($data['stats'])
            : null;

        $topLinks = null;

        if (is_array($data['top_links'] ?? null)) {
            $topLinks = [];

            foreach ($data['top_links'] as $link) {
                if (is_array($link)) {
                    $topLinks[] = CampaignTopLink::fromArray($link);
                }
            }
        }

        $variants = null;

        if (is_array($data['variants'] ?? null)) {
            $variants = [];

            foreach ($data['variants'] as $variant) {
                if (is_array($variant)) {
                    $variants[] = CampaignVariant::fromArray($variant);
                }
            }
        }

        $abTest = is_array($data['ab_test'] ?? null)
            ? CampaignAbTest::fromArray(self::withVariantLocales($data))
            : null;

        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            recipientsTotal: isset($data['recipients_total']) ? (int) $data['recipients_total'] : null,
            dispatchedTotal: isset($data['dispatched_total']) ? (int) $data['dispatched_total'] : null,
            sentCount: isset($data['sent_count']) ? (int) $data['sent_count'] : null,
            failedCount: isset($data['failed_count']) ? (int) $data['failed_count'] : null,
            scheduledAt: isset($data['scheduled_at']) ? (string) $data['scheduled_at'] : null,
            startedAt: isset($data['started_at']) ? (string) $data['started_at'] : null,
            finishedAt: isset($data['finished_at']) ? (string) $data['finished_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            stats: $stats,
            topLinks: $topLinks,
            variants: $variants,
            abTest: $abTest,
            inArchive: isset($data['in_archive']) ? (bool) $data['in_archive'] : null,
            premium: isset($data['premium']) ? (bool) $data['premium'] : null,
            localeVariants: CampaignLocaleVariant::listFrom($data['locale_variants'] ?? null),
        );
    }

    /**
     * Fold the per-A/B-variant translations the API returns under
     * `locales.variants[]` into the matching `ab_test.variants[]` row, so one
     * variant object carries everything that was authored against it.
     *
     * Kept out of {@see CampaignAbTest} on purpose: `ab_test` and `locales` are
     * sibling keys of the campaign payload, so the campaign is the only level
     * that sees both. Pairing is by variant id — the only stable handle, since
     * labels are reassigned server-side on every write.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> The `ab_test` block, translations attached.
     */
    private static function withVariantLocales(array $data): array
    {
        /** @var array<string, mixed> $abTest */
        $abTest = $data['ab_test'];

        $locales = $data['locales'] ?? null;

        if (! is_array($locales) || ! is_array($locales['variants'] ?? null) || ! is_array($abTest['variants'] ?? null)) {
            return $abTest;
        }

        $byId = [];

        foreach ($locales['variants'] as $row) {
            if (is_array($row) && isset($row['id'])) {
                $byId[(int) $row['id']] = $row['locale_variants'] ?? null;
            }
        }

        $abTest['variants'] = array_map(
            function ($variant) use ($byId) {
                if (is_array($variant) && isset($variant['id']) && array_key_exists((int) $variant['id'], $byId)) {
                    $variant['locale_variants'] = $byId[(int) $variant['id']];
                }

                return $variant;
            },
            $abTest['variants'],
        );

        return $abTest;
    }
}
