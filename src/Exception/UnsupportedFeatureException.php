<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown when a send relies on a feature the platform /send API does not
 * support, or one the SDK configuration disables (for example file attachments
 * with `recado-sdk.mail.attachments = 'fail'`). It is a hard, local failure
 * raised before any HTTP request, so the developer must fix the message or
 * adjust the SDK configuration rather than have content silently dropped.
 */
class UnsupportedFeatureException extends RecadoException {}
