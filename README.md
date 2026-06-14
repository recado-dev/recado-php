# Mailer PHP SDK

Official PHP SDK for the **Mailer** REST API v1. It wraps the transactional
send, contacts, lists, tags, templates, messages and campaigns endpoints behind typed
resources and readonly DTOs, with first-class error handling and idempotency
support.

- PHP >= 8.2
- Guzzle ^7 (the only runtime dependency)
- Optional Laravel auto-discovery (works in plain PHP without `illuminate/*`)

## Installation

This package lives inside the Mailer monorepo. Until it is published to
Packagist, install it via a Composer **path repository**:

```jsonc
{
    "repositories": [
        {
            "type": "path",
            "url": "sdk/php"
        }
    ],
    "require": {
        "mailer/mailer-php": "*"
    }
}
```

```bash
composer require mailer/mailer-php
```

Once published (Packagist or a private VCS repository), drop the
`repositories` block and require it by version constraint as usual. For a
private Git host use a `"type": "vcs"` repository pointing at the SDK repo.

## Quick start (plain PHP)

```php
use Mailer\Sdk\MailerClient;

$client = new MailerClient(
    baseUrl: 'https://app.example.com/api/v1',
    token: 'your-project-api-key',
);

// Send a single transactional email (inline content)
$sent = $client->send()->email([
    'to' => 'jane@example.com',
    'subject' => 'Welcome aboard',
    'body' => '<p>Hi {{ contact.first_name }}!</p>',
    'text' => 'Hi!',
    'variables' => ['plan' => 'pro'],
]);

echo $sent->id;      // "11111111-2222-..."
echo $sent->status;  // "queued"

// Or send using a stored template by slug
$client->send()->email([
    'to' => 'jane@example.com',
    'template' => 'welcome',
    'variables' => ['first_name' => 'Jane'],
]);
```

### Batch sends

```php
$result = $client->send()->batch([
    ['to' => 'a@example.com', 'template' => 'welcome'],
    ['to' => 'b@example.com', 'subject' => 'Hi', 'body' => '<p>Hello</p>'],
]);

echo $result->queued; // 2
echo $result->failed; // 0

foreach ($result->messages as $item) {
    // $item->index, $item->status (queued|suppressed|failed), $item->id, $item->code, $item->error
}
```

### Idempotency

`email()` and `batch()` accept a named `idempotencyKey` argument, sent as the
`Idempotency-Key` header. Re-sending with the same key returns the original
result instead of creating a duplicate (a conflicting reuse raises a
`ValidationException` with code `idempotency_conflict`).

```php
$client->send()->email(
    ['to' => 'jane@example.com', 'template' => 'welcome'],
    idempotencyKey: 'order-1234-welcome',
);

$client->send()->batch($messages, idempotencyKey: 'nightly-digest-2026-06-12');
```

### Other resources

```php
// Track an event
$client->send()->track('order.placed', 'jane@example.com', ['total' => 4200]);

// Subscribe a contact (double opt-in aware)
$client->send()->subscribe([
    'email' => 'jane@example.com',
    'first_name' => 'Jane',
    'lists' => [7],
    'tags' => ['newsletter'],
]);

// Contacts
$page = $client->contacts()->list(['status' => 'subscribed', 'per_page' => 50]);
$contact = $client->contacts()->get('jane@example.com');
$client->contacts()->update('jane@example.com', ['first_name' => 'Janet']);
$client->contacts()->tags('jane@example.com', add: ['vip'], remove: ['trial']);
$client->contacts()->cancelAutomationRuns('jane@example.com', automation: 12);
$client->contacts()->delete('jane@example.com'); // GDPR erase

// Lists
$lists = $client->lists()->list();
$list = $client->lists()->create('Newsletter', 'Weekly digest');
$client->lists()->attachContact($list->id, 'jane@example.com');
$client->lists()->detachContact($list->id, 'jane@example.com');

// Tags (flat array)
$tags = $client->tags()->list();

// Templates
$templates = $client->templates()->list();
$template = $client->templates()->create([
    'name' => 'Welcome',
    'slug' => 'welcome',
    'subject' => 'Welcome!',
    'body_html' => '<p>Hi</p>',
]);
$client->templates()->putVariant('welcome', 'es', [
    'subject' => '¡Bienvenido!',
    'body_html' => '<p>Hola</p>',
]);

// Messages (read-only)
$messages = $client->messages()->list(['status' => 'delivered']);
$message = $client->messages()->get('11111111-2222-...');
foreach ($message->events as $event) {
    // $event->type, $event->payload, $event->occurredAt
}

// Campaigns (read-only by design — no send/schedule from the SDK)
$campaigns = $client->campaigns()->list(['per_page' => 50]);
$campaign = $client->campaigns()->get(7);
// $campaign->stats is a populated CampaignStats only on get():
echo $campaign->stats->openRate ?? 0; // rates are null when undefined
```

The campaigns resource is intentionally read-only: it never sends or schedules
a campaign. Trigger mass sends from the dashboard, not the SDK.

Paginated endpoints return a `Paginated` DTO exposing `->data` (mapped DTOs),
`->meta` and `->links`. The tags endpoint returns a flat `Tag[]` array.

### Automatic pagination

Paginated resources also expose a `cursor()` generator that lazily walks every
page for you, fetching one page at a time and yielding each mapped DTO. You
never track page numbers — just iterate:

```php
foreach ($client->contacts()->cursor(['status' => 'subscribed']) as $contact) {
    echo $contact->email.PHP_EOL;
}

// Available cursors (each yields the same DTOs as the matching list()):
$client->contacts()->cursor($query);          // Contact
$client->messages()->cursor($query);          // Message
$client->campaigns()->cursor($query);         // Campaign
$client->lists()->cursor($query);             // ContactList
$client->lists()->contactsCursor($id, $query); // Contact
$client->templates()->cursor($query);         // Template
```

A `cursor()` returns a plain `\Generator` (the core SDK never depends on
Illuminate). In a Laravel app you can wrap it in a `LazyCollection` to use the
collection pipeline:

```php
use Illuminate\Support\LazyCollection;

LazyCollection::make($client->contacts()->cursor())
    ->filter(fn ($contact) => $contact->status === 'subscribed')
    ->each(fn ($contact) => /* ... */);
```

## Resilience

When you let the SDK build its own HTTP client (the default — you do not inject
a Guzzle client), it installs an automatic retry middleware with exponential
backoff.

- **What is retried:** network/connection errors, `5xx` responses, and `429`
  rate-limit responses.
- **Idempotency safety:** only requests that are safe to repeat are retried —
  `GET`/`HEAD`/`OPTIONS`/`PUT`/`DELETE`, or any request that carries an
  `Idempotency-Key` header. A `POST`/`PATCH` **without** an `Idempotency-Key`
  (e.g. an `email()`/`batch()` send made without `idempotencyKey:`) is **never**
  retried, so the transport can never duplicate a send. Pass an idempotency key
  to make sends retry-safe.
- **Retry-After:** on a `429`, the `Retry-After` header is honored (numeric
  seconds or an HTTP-date); otherwise the delay is exponential
  (`min(retry_max_delay, retry_base_delay * 2 ^ attempt)`, no jitter).

Tune it through the optional `options` argument (4th constructor parameter):

```php
$client = new MailerClient(
    baseUrl: 'https://app.example.com/api/v1',
    token: 'your-project-api-key',
    httpClient: null,
    options: [
        'retries' => 2,            // max retry attempts
        'retry_base_delay' => 200, // ms, exponential backoff base
        'retry_max_delay' => 5000, // ms, backoff cap
        'retry_on_status' => range(500, 599), // statuses to retry (429 always retried)
        'timeout' => 30,           // Guzzle request timeout (seconds)
        'connect_timeout' => 10,   // Guzzle connect timeout (seconds)
    ],
);
```

When you inject your own Guzzle client it is used **as-is** — add the retry
middleware yourself via `Mailer\Sdk\Http\RetryMiddleware::make()` if you want it.

## Laravel usage

The package is auto-discovered. Publish the config and set the env vars:

```bash
php artisan vendor:publish --tag=mailer-sdk-config
```

```dotenv
MAILER_BASE_URL=https://app.example.com/api/v1
MAILER_API_TOKEN=your-project-api-key
```

Resolve the client from the container:

```php
use Mailer\Sdk\MailerClient;

public function __construct(private MailerClient $mailer) {}

// ...
$this->mailer->send()->email([...]);
```

The published config also exposes the HTTP resilience knobs (wired into the
container-bound client automatically) and the mail-transport options:

```dotenv
MAILER_TIMEOUT=10
MAILER_RETRIES=2
MAILER_RETRY_BASE_DELAY=200
MAILER_RETRY_MAX_DELAY=5000
MAILER_MAIL_ATTACHMENTS=fail        # fail | ignore
MAILER_MAIL_IDEMPOTENCY=content     # content | random | off
```

## Laravel integration — Mail transport

The package registers a `mailer` mail driver, so you can route Laravel's `Mail`
facade (and notifications, queued mailers, etc.) through the platform `/send`
API without changing any mailing code.

Point Laravel at it. Add a mailer entry to `config/mail.php`:

```php
'mailers' => [
    // ...
    'mailer' => [
        'transport' => 'mailer',
    ],
],
```

and select it:

```dotenv
MAIL_MAILER=mailer
```

Now every send flows through the API:

```php
// Plain message
Mail::raw('Hello world', fn ($m) => $m->to('jane@example.com')->subject('Hi'));

// A Mailable
Mail::to('jane@example.com')->send(new WelcomeMail($user));
```

The transport maps the message to a single `/send` call (one recipient) or a
`/send/batch` call (multiple recipients), reading the subject, HTML body and
text body off the message.

### Sending with a stored template

To render a platform template by slug instead of inline HTML, set the template
headers on the underlying Symfony message from your Mailable. The transport then
sends a template payload (`{to, template, variables}`) and ignores the inline
subject/body:

```php
use Mailer\Sdk\Laravel\Mail\MailerHeaders;

class WelcomeMail extends Mailable
{
    public function build()
    {
        return $this
            ->subject('Welcome') // ignored when a template header is present
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                $headers->addTextHeader(MailerHeaders::TEMPLATE, 'welcome');
                $headers->addTextHeader(
                    MailerHeaders::VARIABLES,
                    json_encode(['first_name' => 'Jane']),
                );
            });
    }
}
```

### Limitations & behavior

The platform `/send` API is intentionally narrow; the transport adapts to it
with explicit, documented behavior rather than silent surprises.

- **From / Reply-To are ignored.** The API does not accept `from` or `reply_to`
  — the platform always uses the project's configured sender (set the project
  `default_from_email`/`default_from_name` and a verified sending domain in the
  dashboard). A `From` set on the message is logged at debug level and dropped.
- **Attachments are not supported.** The `/send` API has no attachment field.
  By default (`mailer-sdk.mail.attachments = 'fail'`) a message carrying an
  attachment throws `Mailer\Sdk\Exception\UnsupportedFeatureException`, so the
  send fails loudly and you fix the Mailable. Set it to `'ignore'` to log a
  warning and send the message *without* the attachments. Attachments are never
  dropped silently in `'fail'` mode.
- **Suppressed recipients are not failures.** When the platform rejects an
  address as suppressed (`recipient_suppressed`), the transport does **not**
  throw: it logs a warning and dispatches a
  `Mailer\Sdk\Laravel\Events\MessageSuppressed` event (carrying the recipient
  email and reason). In a batch send, each suppressed recipient gets its own
  event while the rest are delivered. Listen for the event to prune your lists.
- **Quota / sending-domain rejections are failures.** `quota_exceeded` and
  `sending_domain_not_verified` (and any other unexpected API error) are
  re-thrown as a `Symfony\Component\Mailer\Exception\TransportException` with the
  SDK exception kept as `previous`, so Laravel's mailer/queue treats the send as
  failed and can retry per your own policy. A batch send throws if any recipient
  hard-fails, summarizing the failed recipients.
- **Multiple recipients become a batch.** To + Cc + Bcc are merged into the
  delivery list; each recipient is sent its own copy via `/send/batch` (the
  message content is shared, the `to` differs per item).
- **Idempotency is automatic and retry-safe.** Per send the transport sets an
  `Idempotency-Key`:
  - `content` (default): a deterministic hash of the message content, so a
    requeued job never duplicates the send. Two genuinely identical messages
    sent within the platform's idempotency window dedup — switch to `random` if
    that is not what you want.
  - `random`: a fresh UUID per send attempt (no dedup).
  - `off`: no key.
  Override per message with the `X-Mailer-Idempotency-Key`
  (`MailerHeaders::IDEMPOTENCY_KEY`) header.

## Error handling

Every non-2xx response is mapped to a typed exception. All exceptions extend
`Mailer\Sdk\Exception\MailerException` and expose:

- `getErrorCode(): ?string` — the machine `code` field
- `getStatus(): ?int` — the HTTP status
- `getBody(): ?array` — the raw decoded response envelope
- `getMessage(): string` — the human-facing message (standard `\Exception`)

| Exception                 | HTTP status | Notes |
| ------------------------- | ----------- | ----- |
| `AuthenticationException` | 401         | Missing/invalid/expired token. |
| `NotFoundException`       | 404         | e.g. `contact_not_found`, `template_not_found`, `message_not_found`. |
| `ValidationException`     | 422         | Validation failures and domain rejections (`recipient_suppressed`, `quota_exceeded`, `template_not_found`, `invalid_status_transition`, ...). Adds `errors(): array` (field => messages). |
| `RateLimitException`      | 429         | Adds `retryAfter(): ?int` parsed from the `Retry-After` header. |
| `MailerException`         | any other   | Base class; also the catch-all for unexpected non-2xx statuses. |

```php
use Mailer\Sdk\Exception\ValidationException;
use Mailer\Sdk\Exception\RateLimitException;
use Mailer\Sdk\Exception\MailerException;

try {
    $client->send()->email(['to' => 'jane@example.com', 'template' => 'welcome']);
} catch (ValidationException $e) {
    if ($e->getErrorCode() === 'recipient_suppressed') {
        // address is on the suppression list — skip it
    }
    $fieldErrors = $e->errors(); // ['to' => ['The to field is required.'], ...]
} catch (RateLimitException $e) {
    sleep($e->retryAfter() ?? 1);
    // ...retry
} catch (MailerException $e) {
    report($e);
}
```

## Development

```bash
composer install
vendor/bin/phpunit
```

Tests run entirely against a Guzzle `MockHandler` — no network access required.
