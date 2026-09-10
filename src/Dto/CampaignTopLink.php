<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One row of the click report returned by
 * GET /campaigns/{id}?include=top_links (the 10 most-clicked URLs).
 */
final readonly class CampaignTopLink
{
    public function __construct(
        public ?string $url,
        public ?int $clicks,
        public ?int $uniqueClicks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: isset($data['url']) ? (string) $data['url'] : null,
            clicks: isset($data['clicks']) ? (int) $data['clicks'] : null,
            uniqueClicks: isset($data['unique_clicks']) ? (int) $data['unique_clicks'] : null,
        );
    }
}
