<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown by `campaigns()->send()` when the caller did not pass `confirm: true`.
 *
 * Sending a campaign fires real mail at a real audience and cannot be undone
 * once the batch is queued, so the SDK requires the intent to be spelled out at
 * the call site. This is a purely local guard: it is raised BEFORE any HTTP
 * request, so a `send()` without confirmation never reaches the API.
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
}
