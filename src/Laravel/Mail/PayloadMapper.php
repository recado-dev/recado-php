<?php

declare(strict_types=1);

namespace Recado\Sdk\Laravel\Mail;

use Recado\Sdk\Exception\AttachmentsTooLargeException;
use Recado\Sdk\Exception\UnsupportedFeatureException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Maps a Symfony Email into the platform /send content payload. Either a
 * template payload (when the X-Recado-Template header is present) or an inline
 * subject/body one; in both cases the message's attachments are mapped to the
 * /send `attachments` field per the configured mode (see
 * `recado-sdk.mail.attachments`: 'send' maps them, 'ignore' drops them with a
 * PSR-3 warning, 'fail' throws). An optional PSR-3 logger receives the
 * attachment-ignore warning and the From-set debug log.
 */
final class PayloadMapper
{
    /**
     * Maximum DECODED size of all attachments on one send, mirroring the
     * platform limit (a larger send would only be rejected with a 422
     * `attachments_too_large` after uploading the whole base64 body).
     */
    public const MAX_TOTAL_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    /**
     * Filename used for an unnamed attachment (an extension inferred from the
     * media type is appended when one is known).
     */
    private const FALLBACK_FILENAME = 'attachment';

    /**
     * Build the content payload shared by every recipient (no `to` key). Either
     * a template payload or an inline subject/body one, plus the mapped
     * `attachments` when the message carries any and the mode allows sending.
     *
     * @param array<string, mixed> $mailConfig
     *
     * @return array<string, mixed>
     */
    public static function base(Email $email, array $mailConfig, ?LoggerInterface $logger = null): array
    {
        $attachments = self::attachments($email, $mailConfig, $logger);
        self::warnIfFromIsSet($email, $logger);

        $template = self::header($email, RecadoHeaders::TEMPLATE);

        if ($template !== null) {
            $payload = [
                'template' => $template,
                'variables' => self::variables($email),
            ];

            return self::withAttachments($payload, $attachments);
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        $payload = [
            'subject' => (string) $email->getSubject(),
            // The API requires `body`; fall back to the text body when the
            // message is text-only so a plain Mail::raw() still goes out.
            'body' => self::stringBody($html) ?? self::stringBody($text) ?? '',
        ];

        $textBody = self::stringBody($text);

        if ($textBody !== null) {
            $payload['text'] = $textBody;
        }

        $variables = self::variables($email);

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        return self::withAttachments($payload, $attachments);
    }

    /**
     * Build the full /send payload for a single recipient: `to` first, then the
     * shared content payload.
     *
     * @param array<string, mixed> $mailConfig
     *
     * @return array<string, mixed>
     */
    public static function fromEmail(Email $email, string $recipient, array $mailConfig, ?LoggerInterface $logger = null): array
    {
        return ['to' => $recipient] + self::base($email, $mailConfig, $logger);
    }

    /**
     * Resolve the message's attachments per the configured mode:
     *
     *   - 'send' (default): map each attachment to the API shape
     *     `{filename, content_type, content(base64)}`, throwing an
     *     {@see AttachmentsTooLargeException} early when the decoded total
     *     exceeds the platform's per-send limit. Per-file limits and the
     *     executable-extension blocklist stay server-side.
     *   - 'ignore': drop them with a PSR-3 warning.
     *   - 'fail': throw an {@see UnsupportedFeatureException} (legacy fail-loud
     *     behavior for consumers who never want attachments to leave the app).
     *
     * @param array<string, mixed> $mailConfig
     *
     * @return array<int, array{filename: string, content_type: string, content: string}>
     */
    private static function attachments(Email $email, array $mailConfig, ?LoggerInterface $logger): array
    {
        $parts = $email->getAttachments();

        if ($parts === []) {
            return [];
        }

        $mode = (string) ($mailConfig['attachments'] ?? 'send');

        if ($mode === 'ignore') {
            $logger?->warning(
                'Recado SDK transport: dropping attachments — recado-sdk.mail.attachments is set to "ignore".',
            );

            return [];
        }

        if ($mode === 'fail') {
            throw new UnsupportedFeatureException(
                'Attachment sending is disabled (recado-sdk.mail.attachments = "fail"). '
                .'Remove the attachment from the Mailable, or set the mode to "send" to map it '
                .'onto the /send attachments field (or "ignore" to send the message without it).',
            );
        }

        $attachments = [];
        $totalBytes = 0;

        foreach ($parts as $part) {
            $body = $part->getBody();
            $totalBytes += strlen($body);

            if ($totalBytes > self::MAX_TOTAL_ATTACHMENT_BYTES) {
                throw AttachmentsTooLargeException::forTotalBytes($totalBytes, self::MAX_TOTAL_ATTACHMENT_BYTES);
            }

            $attachments[] = [
                'filename' => self::filename($part),
                'content_type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'content' => base64_encode($body),
            ];
        }

        return $attachments;
    }

    /**
     * The attachment's own filename, or a generic fallback with an extension
     * inferred from the media type for unnamed parts.
     */
    private static function filename(DataPart $part): string
    {
        $filename = $part->getFilename();

        if ($filename !== null && $filename !== '') {
            return $filename;
        }

        $extensions = MimeTypes::getDefault()->getExtensions(
            $part->getMediaType().'/'.$part->getMediaSubtype(),
        );

        return $extensions === []
            ? self::FALLBACK_FILENAME
            : self::FALLBACK_FILENAME.'.'.$extensions[0];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array{filename: string, content_type: string, content: string}> $attachments
     *
     * @return array<string, mixed>
     */
    private static function withAttachments(array $payload, array $attachments): array
    {
        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    private static function warnIfFromIsSet(Email $email, ?LoggerInterface $logger): void
    {
        if ($email->getFrom() !== []) {
            $logger?->debug(
                'Recado SDK transport: ignoring the message From address; the platform uses the '
                ."project's configured sender.",
            );
        }
    }

    /**
     * Decode the X-Recado-Variables JSON header into an associative array.
     *
     * @return array<string, mixed>
     */
    private static function variables(Email $email): array
    {
        $raw = self::header($email, RecadoHeaders::VARIABLES);

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function header(Email $email, string $name): ?string
    {
        $header = $email->getHeaders()->get($name);

        if ($header === null) {
            return null;
        }

        return $header->getBodyAsString();
    }

    /**
     * Symfony body parts can be strings or resources; normalize to a string.
     */
    private static function stringBody(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_resource($body)) {
            $contents = stream_get_contents($body);

            return $contents === false ? null : $contents;
        }

        return (string) $body;
    }

    private function __construct()
    {
    }
}
