<?php

declare(strict_types=1);

namespace Mailer\Sdk;

use GuzzleHttp\ClientInterface;
use Mailer\Sdk\Http\HttpClient;
use Mailer\Sdk\Resources\ContactsResource;
use Mailer\Sdk\Resources\ListsResource;
use Mailer\Sdk\Resources\MessagesResource;
use Mailer\Sdk\Resources\SendResource;
use Mailer\Sdk\Resources\TagsResource;
use Mailer\Sdk\Resources\TemplatesResource;

/**
 * Entry point of the Mailer SDK. Build it with a base URL (the ".../api/v1"
 * root) and a project API token, then reach the API through memoized resource
 * accessors. A custom Guzzle client may be injected (used in tests).
 */
final class MailerClient
{
    private readonly HttpClient $http;

    private ?SendResource $send = null;

    private ?ContactsResource $contacts = null;

    private ?ListsResource $lists = null;

    private ?TagsResource $tags = null;

    private ?TemplatesResource $templates = null;

    private ?MessagesResource $messages = null;

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
        $this->http = new HttpClient(rtrim($baseUrl, '/'), $token, $httpClient, $options);
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
}
