<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

/**
 * Thrown when the SDK is constructed with missing or placeholder configuration
 * (an unset/empty/placeholder base URL or an empty API token). It is a hard,
 * local failure raised before any HTTP request, so a misconfigured consumer
 * fails loudly at construction instead of silently sending to a dead host.
 */
class RecadoConfigurationException extends RecadoException {}
