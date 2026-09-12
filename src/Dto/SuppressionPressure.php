<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * The project's suppression pressure (GET /delivery/health).
 *
 * `global` counts only THIS project's own contacts that sit on the shared
 * platform-wide list — its total size is never exposed to a tenant. `scoped`
 * counts the project's own rows (BYO provider feedback) wholesale, so it may
 * include transactional recipients that never became contacts.
 */
final readonly class SuppressionPressure
{
    public function __construct(
        public int $global,
        public int $scoped,
        public int $contactsBounced,
        public int $contactsComplained,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            global: (int) ($data['global'] ?? 0),
            scoped: (int) ($data['scoped'] ?? 0),
            contactsBounced: (int) ($data['contacts_bounced'] ?? 0),
            contactsComplained: (int) ($data['contacts_complained'] ?? 0),
        );
    }
}
