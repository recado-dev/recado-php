<?php

declare(strict_types=1);

namespace Recado\Sdk;

use GuzzleHttp\ClientInterface;
use Recado\Sdk\Exception\RecadoConfigurationException;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\CampaignsResource;
use Recado\Sdk\Resources\ContactsResource;
use Recado\Sdk\Resources\ListsResource;
use Recado\Sdk\Resources\MessagesResource;
use Recado\Sdk\Resources\NotificationsResource;
use Recado\Sdk\Resources\PushTokensResource;
use Recado\Sdk\Resources\SandboxResource;
use Recado\Sdk\Resources\SendResource;
use Recado\Sdk\Resources\TagsResource;
use Recado\Sdk\Resources\TemplatesResource;

/**
 * Entry point of the Recado SDK. Build it with a base URL (the ".../api/v1"
 * root) and a project API token, then reach the API through memoized resource
 * accessors. A custom Guzzle client may be injected (used in tests).
 */
final class RecadoClient
{
    /**
     * The placeholder host the SDK used to ship as a working-looking default.
     * A base URL pointing at it is treated as "not configured" so a consumer
     * who forgot to set RECADO_BASE_URL (or still has the old published
     * default) fails loudly instead of silently sending to a dead host.
     */
    public const string PLACEHOLDER_BASE_URL_HOST = 'api.mailer.test';

    /**
     * The pre-rebrand hosted host (`mosaiqo/mailer-php` v1.x default). It is
     * being decommissioned, so a stale config still pointing at it must fail
     * loudly at construction instead of POSTing into the void.
     */
    public const string LEGACY_BASE_URL_HOST = 'mailer.mosaiqo.com';

    /**
     * The hosted API's canonical base URL, used as the default when
     * RECADO_BASE_URL is not set. Hosted consumers only need to configure
     * RECADO_API_TOKEN; self-hosted consumers override RECADO_BASE_URL with
     * their own endpoint. The legacy apex path (https://recado.dev/api/v1)
     * remains supported — a consumer may pin it deliberately.
     */
    public const string DEFAULT_BASE_URL = 'https://api.recado.dev/v1';

    private readonly HttpClient $http;

    private ?SendResource $send = null;

    private ?ContactsResource $contacts = null;

    private ?ListsResource $lists = null;

    private ?TagsResource $tags = null;

    private ?TemplatesResource $templates = null;

    private ?MessagesResource $messages = null;

    private ?CampaignsResource $campaigns = null;

    private ?NotificationsResource $notifications = null;

    private ?PushTokensResource $push = null;

    private ?SandboxResource $sandbox = null;

    /**
     * @param array<string, mixed> $options Transport/resilience options applied
     *                                       only when no client is injected:
     *                                       `retries`, `retry_base_delay`,
     *                                       `retry_max_delay`, `retry_on_status`,
     *                                       `timeout`, `connect_timeout`.
     */
    public function __construct(
        string $baseUrl,
        string $token,
        ?ClientInterface $httpClient = null,
        array $options = [],
    ) {
        self::assertConfigured($baseUrl, $token);

        $this->http = new HttpClient(rtrim($baseUrl, '/'), $token, $httpClient, $options);
    }

    /**
     * Fail loudly on missing/placeholder/dead-host configuration before any
     * request is ever made, so a misconfigured consumer gets a clear error
     * instead of silently sending to a dead host (the old `api.mailer.test`
     * placeholder or the decommissioned `mailer.mosaiqo.com` v1.x host) or
     * with an empty Bearer token.
     *
     * @throws RecadoConfigurationException
     */
    private static function assertConfigured(string $baseUrl, string $token): void
    {
        if (trim($baseUrl) === '') {
            throw new RecadoConfigurationException(
                'RECADO_BASE_URL is not configured; set it to your Recado API endpoint '
                .'(e.g. https://api.recado.dev/v1).',
            );
        }

        if (str_contains($baseUrl, self::PLACEHOLDER_BASE_URL_HOST)) {
            throw new RecadoConfigurationException(
                'RECADO_BASE_URL is still set to the placeholder "'.self::PLACEHOLDER_BASE_URL_HOST.'"; '
                .'set it to your Recado API endpoint (e.g. https://api.recado.dev/v1).',
            );
        }

        if (str_contains($baseUrl, self::LEGACY_BASE_URL_HOST)) {
            throw new RecadoConfigurationException(
                'RECADO_BASE_URL still points at the decommissioned "'.self::LEGACY_BASE_URL_HOST.'" host; '
                .'the platform moved to https://recado.dev — set RECADO_BASE_URL to https://api.recado.dev/v1 '
                .'(or your self-hosted /api/v1 endpoint).',
            );
        }

        if (trim($token) === '') {
            throw new RecadoConfigurationException(
                'RECADO_API_TOKEN is not configured; set it to a project API key '
                .'(Recado → Settings → API keys).',
            );
        }
    }

    public function send(): SendResource
    {
        return $this->send ??= new SendResource($this->http);
    }

    public function contacts(): ContactsResource
    {
        return $this->contacts ??= new ContactsResource($this->http);
    }

    public function lists(): ListsResource
    {
        return $this->lists ??= new ListsResource($this->http);
    }

    public function tags(): TagsResource
    {
        return $this->tags ??= new TagsResource($this->http);
    }

    public function templates(): TemplatesResource
    {
        return $this->templates ??= new TemplatesResource($this->http);
    }

    public function messages(): MessagesResource
    {
        return $this->messages ??= new MessagesResource($this->http);
    }

    public function campaigns(): CampaignsResource
    {
        return $this->campaigns ??= new CampaignsResource($this->http);
    }

    public function notifications(): NotificationsResource
    {
        return $this->notifications ??= new NotificationsResource($this->http);
    }

    public function push(): PushTokensResource
    {
        return $this->push ??= new PushTokensResource($this->http);
    }

    public function sandbox(): SandboxResource
    {
        return $this->sandbox ??= new SandboxResource($this->http);
    }
}
