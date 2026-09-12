<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\ProjectProfile;
use Recado\Sdk\Http\HttpClient;

/**
 * The Project resource (read-only): who this API key acts as.
 *
 * One call instead of guessing — most usefully `ProjectProfile::isSandbox()`,
 * so a client can tell a TEST credential from a production one before it writes
 * anything. Works in a sandbox on purpose: it is how a sandbox key confirms it
 * is one.
 *
 * Read-only by design. There is no `PATCH /project` and none is faked here:
 * project settings (sending identity, archive, compliance defaults) are a
 * dashboard decision, not an API one.
 */
final readonly class ProjectResource
{
    public function __construct(private HttpClient $http) {}

    /**
     * The project profile (GET /project).
     */
    public function get(): ProjectProfile
    {
        $response = $this->http->get('project');

        return ProjectProfile::fromArray($response['data'] ?? []);
    }
}
