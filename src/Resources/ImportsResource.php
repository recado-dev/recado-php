<?php

declare(strict_types=1);

namespace Recado\Sdk\Resources;

use Recado\Sdk\Dto\Import;
use Recado\Sdk\Dto\Paginated;
use Recado\Sdk\Http\HttpClient;
use Recado\Sdk\Resources\Concerns\PaginatesResults;

/**
 * The Imports resource: bulk contact imports, the JSON twin of the CSV import
 * wizard.
 *
 * This is the only API surface that creates contacts in bulk WITH their consent
 * state: `send()->subscribe()` is one signup at a time and
 * `contacts()->batchUpdate()` deliberately never touches status or list
 * membership. Provider migrations need exactly this — the addresses plus the
 * opt-outs that came with them.
 *
 * Semantics worth knowing before you call it:
 *
 * - Up to 5000 rows per request (more is a whole-request `422`); page a big
 *   migration, each page is its own import with its own id.
 * - ASYNCHRONOUS: `create()` answers `202` with status `pending`. Poll `get()`
 *   until `Import::isFinished()`.
 * - Upsert by email, scoped to the project; attributes MERGE key by key.
 * - An import NEVER resurrects an opt-out: an imported `subscribed` leaves an
 *   unsubscribed/bounced/complained contact in that state, and an
 *   already-unsubscribed contact keeps its original `unsubscribed_at`.
 * - `status` accepts words, never booleans — `true`/`1` are a `422`, because
 *   their meaning flips with the column they came from.
 * - No double opt-in and no events: no confirmation email, no
 *   `contact.subscribed` / `contact.unsubscribed` / `contact.tagged` webhook or
 *   automation storm.
 * - Attributes land as TEXT (CSV semantics: `3` becomes `"3"`); use the
 *   contacts endpoints when the attribute type matters.
 */
final readonly class ImportsResource
{
    use PaginatesResults;

    public function __construct(private HttpClient $http) {}

    /**
     * Queue a bulk import (POST /imports), answering `202`.
     *
     * Per row: `email` (required) plus `first_name`, `last_name`, `locale`,
     * `tags` (max 25 names, none containing a comma), `status`,
     * `unsubscribed_at`, `attributes`. The envelope-level `lists` (ids of this
     * project's lists) and `tags` apply to EVERY row.
     *
     * The plan's contact quota is pre-checked for the whole request assuming
     * every row creates a contact: over the limit is a `422` with the code
     * `quota_exceeded` and nothing is queued. An unknown list id is a `422`
     * with `list_not_found`.
     *
     * @param  array<int, array<string, mixed>>  $rows  1..5000 rows.
     * @param  array<string, mixed>  $options  lists, tags, skip_invalid_emails,
     *                                         skip_risky_emails.
     */
    public function create(array $rows, array $options = []): Import
    {
        $response = $this->http->post('imports', [
            'json' => array_merge($options, ['rows' => array_values($rows)]),
        ]);

        return Import::fromArray($response['data'] ?? []);
    }

    /**
     * List the project's imports, newest first (GET /imports) — CSV-wizard runs
     * included.
     *
     * @param  array<string, mixed>  $query  per_page (max 100), page.
     * @return Paginated<Import>
     */
    public function list(array $query = []): Paginated
    {
        $response = $this->http->get('imports', ['query' => $query]);

        return Paginated::fromArray($response, Import::fromArray(...));
    }

    /**
     * Lazily iterate every import across all pages (GET /imports).
     *
     * @param  array<string, mixed>  $query  per_page (page is managed automatically).
     * @return \Generator<int, Import>
     */
    public function cursor(array $query = []): \Generator
    {
        return $this->paginate(
            fn (int $page): Paginated => $this->list(array_merge($query, ['page' => $page])),
        );
    }

    /**
     * One import with its live progress counters (GET /imports/{id}) — the
     * endpoint you poll until `Import::isFinished()`.
     *
     * An import of another project is a `404` with the code
     * `import_not_found`.
     */
    public function get(int $id): Import
    {
        $response = $this->http->get('imports/'.$id);

        return Import::fromArray($response['data'] ?? []);
    }
}
