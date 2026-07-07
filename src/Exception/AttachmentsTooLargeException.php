<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown locally, before any HTTP request, when the decoded attachments of a
 * single send exceed the platform's total size limit (10 MB decoded per send).
 * Failing early avoids uploading megabytes of base64 just to receive the same
 * rejection as a 422 with code `attachments_too_large` from the API.
 *
 * `getErrorCode()` returns `attachments_too_large` — the same machine code the
 * server uses — so callers can branch on one value for both the local and the
 * server-side rejection.
 */
class AttachmentsTooLargeException extends RecadoException
{
    public static function forTotalBytes(int $totalBytes, int $maxBytes): self
    {
        return new self(
            sprintf(
                'The decoded attachments total %.1f MB, exceeding the %.1f MB per-send limit of the platform /send API.',
                $totalBytes / (1024 * 1024),
                $maxBytes / (1024 * 1024),
            ),
            errorCode: 'attachments_too_large',
        );
    }
}
