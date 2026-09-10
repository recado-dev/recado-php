<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The rendered output of POST /campaigns/{id}/preview — the campaign as a send
 * would render it, without creating a message or sending anything.
 *
 * `preheader` is null when the campaign has none. `unsubscribe_url` renders as
 * an inert `#` anchor inside `html`: it is keyed by the per-recipient message
 * uuid, which a preview by definition does not have.
 */
final readonly class CampaignPreview
{
    public function __construct(
        public ?string $subject,
        public ?string $preheader,
        public ?string $html,
        public ?string $text,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            preheader: isset($data['preheader']) ? (string) $data['preheader'] : null,
            html: isset($data['html']) ? (string) $data['html'] : null,
            text: isset($data['text']) ? (string) $data['text'] : null,
        );
    }
}
