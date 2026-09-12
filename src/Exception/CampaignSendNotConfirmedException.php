<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown by `campaigns()->send()` and `broadcasts()->send()` when the caller
 * did not pass `confirm: true`.
 *
 * A mass send fires real mail (or real notifications) at a real audience and
 * cannot be undone once the batch is queued, so the SDK requires the intent to
 * be spelled out at the call site. This is a purely local guard: it is raised
 * BEFORE any HTTP request, so a `send()` without confirmation never reaches the
 * API. One exception type covers both surfaces, so a caller catches once.
 */
class CampaignSendNotConfirmedException extends RecadoException
{
    public static function forCampaign(int|string $id): self
    {
        return new self(
            'Refusing to send campaign '.$id.' without an explicit confirmation. '
            .'A campaign send fires real mail at a real audience and cannot be recalled — '
            .'call send($id, confirm: true) when that is what you mean.',
        );
    }

    public static function forBroadcast(int|string $id): self
    {
        return new self(
            'Refusing to send broadcast '.$id.' without an explicit confirmation. '
            .'A broadcast send fires real notifications at a real audience and cannot be recalled — '
            .'call send($id, confirm: true) when that is what you mean.',
        );
    }
}
