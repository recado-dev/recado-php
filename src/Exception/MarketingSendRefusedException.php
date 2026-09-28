<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown on a `422` that refused a MARKETING send (`/send` with
 * `marketing: true`) for a reason that only marketing mail has:
 *
 * - `contact_not_found` — a marketing send never creates a contact;
 * - `recipient_not_subscribed` — the contact is unconfirmed or unsubscribed;
 * - `category_not_found` — no PUBLIC tag with that name in the project;
 * - `recipient_not_in_category` — the contact does not carry the category tag;
 * - `frequency_cap_reached` — the contact already got the project's weekly
 *   maximum of marketing messages;
 * - `cloudflare_marketing_not_acknowledged` — the project's marketing stream is
 *   Cloudflare Email Service and nobody acknowledged its marketing terms yet.
 *
 * All of them are about the recipient or the project, not about the request
 * shape, so retrying the same call gives the same answer. The shared refusals
 * (`recipient_suppressed`, `sending_provider_required`,
 * `sending_domain_not_verified`, ...) stay plain `ValidationException`s. It
 * extends `ValidationException`, so existing catch blocks keep working. In a
 * batch the same codes are per-item results (`BatchItem::$code`).
 */
final class MarketingSendRefusedException extends ValidationException
{
    /** @var array<int, string> */
    public const array CODES = [
        'contact_not_found',
        'recipient_not_subscribed',
        'category_not_found',
        'recipient_not_in_category',
        'frequency_cap_reached',
        'cloudflare_marketing_not_acknowledged',
    ];

    public static function handles(?string $code): bool
    {
        return $code !== null && in_array($code, self::CODES, true);
    }

    /**
     * Re-tag a `/send` refusal (the same codes mean something else on other
     * endpoints, so the mapping only happens where the send was made).
     */
    public static function from(ValidationException $exception): self
    {
        return new self(
            $exception->getMessage(),
            $exception->errors(),
            $exception->getErrorCode(),
            $exception->getStatus(),
            $exception->getBody(),
            $exception,
        );
    }
}
