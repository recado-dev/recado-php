<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A contact list. Some fields (`description`, `contactsCount`, `createdAt`) are
 * only present on the dedicated lists endpoints; the abbreviated form embedded
 * in a contact profile carries just `id` and `name` plus the membership fields.
 *
 * Per-list double opt-in: a list with `requiresConfirmation` does not attach a
 * contact who joins through `/contacts/subscribe` or `/track` — it emails a
 * confirmation link instead, and the person becomes a member only after
 * clicking it. `confirmationTemplate` is the slug of the transactional template
 * that email is sent from (null = the built-in email) and `pendingCount` the
 * number of requests still waiting for a click (dedicated lists endpoints only).
 *
 * Embedded in a contact profile the same DTO describes a MEMBERSHIP:
 * - `Contact::$lists` rows carry `status` `confirmed` (a member) and
 *   `confirmedAt` (set when the membership came from a confirmation click,
 *   null when it was created any other way);
 * - `Contact::$pendingLists` rows carry `status` `pending` (NOT a member yet)
 *   with `requestedAt`, `confirmationSentAt` and `expiresAt`.
 */
final readonly class ContactList
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $description,
        public ?int $contactsCount,
        public ?string $createdAt,
        public ?bool $requiresConfirmation = null,
        public ?string $confirmationTemplate = null,
        public ?int $pendingCount = null,
        public ?string $status = null,
        public ?string $confirmedAt = null,
        public ?string $requestedAt = null,
        public ?string $confirmationSentAt = null,
        public ?string $expiresAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            contactsCount: isset($data['contacts_count']) ? (int) $data['contacts_count'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            requiresConfirmation: isset($data['requires_confirmation'])
                ? (bool) $data['requires_confirmation']
                : null,
            confirmationTemplate: isset($data['confirmation_template'])
                ? (string) $data['confirmation_template']
                : null,
            pendingCount: isset($data['pending_count']) ? (int) $data['pending_count'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            confirmedAt: isset($data['confirmed_at']) ? (string) $data['confirmed_at'] : null,
            requestedAt: isset($data['requested_at']) ? (string) $data['requested_at'] : null,
            confirmationSentAt: isset($data['confirmation_sent_at'])
                ? (string) $data['confirmation_sent_at']
                : null,
            expiresAt: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
        );
    }

    /**
     * Whether this row is a pending join request (the person has not clicked
     * the confirmation link yet) rather than a membership.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
