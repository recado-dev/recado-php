<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Contact;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Contacts resource: subscribe, list, fetch, update, delete, tag and
 * cancel automation runs.
 */
final readonly class ContactsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http)
    {
    }

    /**
     * Subscribe a contact (POST /contacts/subscribe).
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed> The `data` block: id, email, status.
     */
    public function subscribe(array $payload): array
    {
        $response = $this->http->post('contacts/subscribe', ['json' => $payload]);

        return $response['data'] ?? [];
    }

    /**
     * List contacts (GET /contacts).
     *
     * @param array<string, mixed> $query search, status, tag_id, list_id,
     *                                    per_page, page.
     *
     * @return Paginated<Contact>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('contacts', ['query' => $query]);

        return Paginated::fromArray($response, Contact::fromArray(...));
    }

    /**
     * Lazily iterate every contact across all pages (GET /contacts).
     *
     * @param array<string, mixed> $query search, status, tag_id, list_id,
     *                                    per_page (page is managed automatically).
     *
     * @return \Generator<int, Contact>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Fetch a single contact's full profile (GET /contacts/{email}).
     */
    public function get(string $email): Contact
    {
        $response = $this->http->get('contacts/'.rawurlencode($email));

        return Contact::fromArray($response['data'] ?? []);
    }

    /**
     * Update a contact (PATCH /contacts/{email}).
     *
     * @param array<string, mixed> $payload Any of first_name, last_name,
     *                                      locale, attributes, status.
     */
    public function update(string $email, array $payload): Contact
    {
        $response = $this->http->patch('contacts/'.rawurlencode($email), ['json' => $payload]);

        return Contact::fromArray($response['data'] ?? []);
    }

    /**
     * Delete (GDPR erase) a contact (DELETE /contacts/{email}).
     */
    public function delete(string $email): void
    {
        $this->http->delete('contacts/'.rawurlencode($email));
    }

    /**
     * Add and/or remove tags on a contact (POST /contacts/{email}/tags).
     *
     * @param array<int, string> $add
     * @param array<int, string> $remove
     *
     * @return array<string, mixed> The `data` block: email, tags (sorted names).
     */
    public function tags(string $email, array $add = [], array $remove = []): array
    {
        $payload = [];

        if ($add !== []) {
            $payload['add'] = array_values($add);
        }

        if ($remove !== []) {
            $payload['remove'] = array_values($remove);
        }

        $response = $this->http->post('contacts/'.rawurlencode($email).'/tags', ['json' => $payload]);

        return $response['data'] ?? [];
    }

    /**
     * Cancel a contact's active automation runs
     * (DELETE /contacts/{email}/automation-runs).
     *
     * @return array<string, mixed> The `data` block: cancelled (count).
     */
    public function cancelAutomationRuns(string $email, ?int $automation = null): array
    {
        $options = [];

        if ($automation !== null) {
            $options['query'] = ['automation' => $automation];
        }

        $response = $this->http->delete('contacts/'.rawurlencode($email).'/automation-runs', $options);

        return $response['data'] ?? [];
    }
}
