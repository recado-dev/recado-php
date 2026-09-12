<?php

declare(strict_types=1);

namespace Recado\Sdk\Exception;

use Recado\Sdk\Dto\VerificationEstimate;

/**
 * Thrown by `verification()->run()` when the platform refused the run because
 * the confirmed figure no longer matches what the scope would bill right now
 * (`422` `estimate_mismatch`).
 *
 * The audience grew, or somebody verified part of it meanwhile. Because these
 * lookups are billed per address with no refund, the platform will not run on a
 * number nobody has seen — so the refusal carries the CURRENT estimate, exposed
 * here as a ready-to-use DTO:
 *
 * ```php
 * try {
 *     $run = $client->verification()->run($estimate);
 * } catch (VerificationEstimateMismatchException $e) {
 *     $fresh = $e->currentEstimate();   // re-confirm a number you have seen
 *     $run = $client->verification()->run($fresh);
 * }
 * ```
 *
 * It extends `ValidationException`, so code that only catches that keeps
 * working.
 */
final class VerificationEstimateMismatchException extends ValidationException
{
    /** @var array<int, int> */
    private array $contactIds = [];

    private ?int $listId = null;

    /**
     * The estimate the platform computed at refusal time, re-scoped to the same
     * list/contact ids the refused run asked for.
     *
     * Null only when the response carried no `estimate` block.
     */
    public function currentEstimate(): ?VerificationEstimate
    {
        $body = $this->getBody() ?? [];

        if (! is_array($body['estimate'] ?? null)) {
            return null;
        }

        return VerificationEstimate::fromArray(
            $body['estimate'],
            $this->listId,
            $this->contactIds,
        );
    }

    /**
     * Re-tag the refusal with the scope the run asked for, so the fresh
     * estimate can be handed straight back to `run()`.
     *
     * @param  array<int, int>  $contactIds
     */
    public static function from(ValidationException $exception, ?int $listId, array $contactIds): self
    {
        $mismatch = new self(
            $exception->getMessage(),
            $exception->errors(),
            $exception->getErrorCode(),
            $exception->getStatus(),
            $exception->getBody(),
            $exception,
        );

        $mismatch->listId = $listId;
        $mismatch->contactIds = $contactIds;

        return $mismatch;
    }
}
