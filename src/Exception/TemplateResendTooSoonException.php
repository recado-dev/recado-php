<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown on `422` `template_resend_too_soon`: the template carries a resend
 * guard (`min_resend_interval_minutes`) and this contact already received it
 * inside that window, so the platform refused the send instead of delivering
 * a duplicate.
 *
 * It is deliberately a 422 and not a 429 — a generic HTTP client auto-retries
 * a 429, which would deliver exactly the duplicate the guard prevents. Wait
 * `retryAfterSeconds()` before sending this template to the contact again, or
 * drop the send.
 *
 * It extends `ValidationException`, so code that only catches that keeps
 * working. In a batch the same refusal is a per-item result instead:
 * `BatchItem::$code === 'template_resend_too_soon'` with
 * `BatchItem::$retryAfterSeconds`.
 */
final class TemplateResendTooSoonException extends ValidationException
{
    public const string CODE = 'template_resend_too_soon';

    /**
     * Seconds until the template may be sent to this contact again, or null
     * when the response carried no hint.
     */
    public function retryAfterSeconds(): ?int
    {
        $value = ($this->getBody() ?? [])['retry_after_seconds'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
