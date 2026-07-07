<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown on HTTP 404 responses (e.g. contact_not_found, template_not_found).
 */
class NotFoundException extends RecadoException
{
}
