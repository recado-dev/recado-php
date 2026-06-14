<?php

declare(strict_types=1);

namespace Mailer\Sdk\Exception;

/**
 * Thrown when a send relies on a feature the platform /send API does not
 * support (for example file attachments). It is a hard, local failure raised
 * before any HTTP request, so the developer must fix the message or adjust the
 * SDK configuration rather than have content silently dropped.
 */
class UnsupportedFeatureException extends MailerException {}
