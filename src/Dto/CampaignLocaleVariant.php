<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * One translation of a campaign: the subject, preheader and body a recipient
 * in that language receives.
 *
 * These are the AUTHORED values, exactly as create() and update() wrote them —
 * a null field means "inherits the layer below" (the A/B variant, then the
 * campaign), which is the distinction a client editing the draft needs. The
 * effective text a given recipient sees is derived at send time and is not
 * what this DTO carries.
 *
 * A translation has no sender and no editor of its own: who a campaign sends
 * from is not a language decision, and `content` always follows the campaign's
 * `editor` shape.
 */
final readonly class CampaignLocaleVariant
{
    /**
     * @param  array<string, mixed>|null  $content  The authored body in the
     *                                              campaign's editor shape;
     *                                              null inherits.
     */
    public function __construct(
        public ?int $id,
        public ?string $locale,
        public ?string $subject = null,
        public ?string $preheader = null,
        public ?array $content = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            locale: isset($data['locale']) ? (string) $data['locale'] : null,
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            preheader: isset($data['preheader']) ? (string) $data['preheader'] : null,
            content: is_array($data['content'] ?? null) ? $data['content'] : null,
        );
    }

    /**
     * Hydrate a `locale_variants` list, tolerating a missing or malformed one.
     *
     * @param  mixed  $rows
     * @return array<int, self>
     */
    public static function listFrom($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $variants = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $variants[] = self::fromArray($row);
            }
        }

        return $variants;
    }
}
