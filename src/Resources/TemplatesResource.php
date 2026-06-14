<?php

declare(strict_types=1);

namespace Mailer\Sdk\Resources;

use Mailer\Sdk\Dto\Paginated;
use Mailer\Sdk\Dto\Template;
use Mailer\Sdk\Dto\TemplateVariant;
use Mailer\Sdk\Http\HttpClient;
use Mailer\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Templates resource: CRUD plus per-locale variants.
 */
final readonly class TemplatesResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http)
    {
    }

    /**
     * List templates (GET /templates) — compact form.
     *
     * @param array<string, mixed> $query
     *
     * @return Paginated<Template>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('templates', ['query' => $query]);

        return Paginated::fromArray($response, Template::fromArray(...));
    }

    /**
     * Lazily iterate every template across all pages (GET /templates).
     *
     * @param array<string, mixed> $query per_page (page is managed automatically).
     *
     * @return \Generator<int, Template>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * Create a template (POST /templates).
     *
     * @param array<string, mixed> $payload name, slug, subject, body_html,
     *                                      optional body_text.
     */
    public function create(array $payload): Template
    {
        $response = $this->http->post('templates', ['json' => $payload]);

        return Template::fromArray($response['data'] ?? []);
    }

    /**
     * Fetch a single template's full form (GET /templates/{slug}).
     */
    public function get(string $slug): Template
    {
        $response = $this->http->get('templates/'.rawurlencode($slug));

        return Template::fromArray($response['data'] ?? []);
    }

    /**
     * Partially update a template (PATCH /templates/{slug}).
     *
     * @param array<string, mixed> $payload Any of name, slug, subject,
     *                                      body_html, body_text.
     */
    public function update(string $slug, array $payload): Template
    {
        $response = $this->http->patch('templates/'.rawurlencode($slug), ['json' => $payload]);

        return Template::fromArray($response['data'] ?? []);
    }

    /**
     * Delete a template (DELETE /templates/{slug}).
     */
    public function delete(string $slug): void
    {
        $this->http->delete('templates/'.rawurlencode($slug));
    }

    /**
     * Create or update a per-locale variant
     * (PUT /templates/{slug}/variants/{locale}).
     *
     * @param array<string, mixed> $payload subject, body_html, optional body_text.
     */
    public function putVariant(string $slug, string $locale, array $payload): TemplateVariant
    {
        $response = $this->http->put(
            'templates/'.rawurlencode($slug).'/variants/'.rawurlencode($locale),
            ['json' => $payload],
        );

        return TemplateVariant::fromArray($response['data'] ?? []);
    }

    /**
     * Delete a per-locale variant
     * (DELETE /templates/{slug}/variants/{locale}).
     */
    public function deleteVariant(string $slug, string $locale): void
    {
        $this->http->delete('templates/'.rawurlencode($slug).'/variants/'.rawurlencode($locale));
    }
}
