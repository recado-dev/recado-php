<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\EmailVerification;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Http\HttpClient;

/**
 * Real-time email verification (POST /verify) — the check a signup backend
 * runs while its user waits:
 *
 * ```php
 * $result = $client->verify()->email('ana@gmial.com');
 *
 * if ($result->didYouMean !== null) {
 *     // "Did you mean ana@gmail.com?"
 * }
 * ```
 *
 * Included in every plan (no external provider, nothing billed per lookup)
 * and nothing is stored: no contact is created. Needs a key with the `verify`
 * scope (or a full-access key); keep it server-side.
 *
 * Every call counts toward the team's monthly verifications (sandbox keys are
 * free). A plan with a monthly cap answers `422` `verification_quota_exceeded`
 * once it is spent — a `ValidationException` whose `getErrorCode()` says so;
 * retrying will not help until the month resets.
 */
final readonly class VerifyResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * Verify one address.
     *
     * A malformed address is not an error: it comes back as `invalid` with
     * reason `syntax_invalid`.
     *
     * @throws ValidationException On a missing/oversized address, or `verification_quota_exceeded`.
     */
    public function email(string $email): EmailVerification
    {
        $response = $this->http->post('verify', ['json' => ['email' => $email]]);

        return EmailVerification::fromArray($response['data'] ?? []);
    }
}
