# Recado PHP SDK

Official PHP SDK for the **Recado** REST API v1. It wraps the transactional
send, contacts, lists, tags, templates, messages and campaigns endpoints behind
typed resources and readonly DTOs, with first-class error handling and
idempotency support — plus an optional, batteries-included Laravel integration.

> ⚠️ **Read-only mirror.** This repo is an automated split of the SDK from a
> private monorepo. **Do not open pull requests here** — they can't be merged
> (the mirror is force-pushed) and will be auto-closed. Found a bug or have a
> request? **Open an issue** (see [CONTRIBUTING](CONTRIBUTING.md)).

> 🤖 **Integrating with an AI agent?** Start with [AGENTS.md](AGENTS.md) — a
> terse, imperative integration playbook with the human-gate steps called out.
> This README is the full human reference.

## Features

- Typed resources + readonly DTOs over the full Recado API v1 surface (send,
  contacts, lists, tags, templates, messages, read-only campaigns).
- A precise exception hierarchy with machine `code` branching.
- Automatic, idempotency-safe retries with exponential backoff.
- Lazy pagination via `cursor()` generators (no page bookkeeping).
- Per-send idempotency keys to make retries duplicate-free.
- Optional Laravel integration (auto-discovered): a `recado` mail transport, a
  `recado` notification channel and a `Recado` facade.
- Zero required dependencies beyond Guzzle; the core works in plain PHP without
  any Illuminate package installed.

## Requirements

- PHP `>= 8.3`
- `guzzlehttp/guzzle` `^7` (the only runtime dependency)
- Laravel `^11.0 || ^12.0 || ^13.0` — **optional**, only for the Laravel
  integration (mail transport, notification channel, facade). The core SDK runs
  fine without it.

## Installation

The package is published on
[Packagist](https://packagist.org/packages/recado/recado-php) — require it
directly with Composer, no repository entry or Git/SSH access needed:

```bash
composer require recado/recado-php:^2.0
```

> **Migrating from `mosaiqo/mailer-php` (v1.x)?** v2.0.0 is the same SDK under
> the new brand — no functional changes, but every brand-carrying name was
> renamed: package `mosaiqo/mailer-php` → `recado/recado-php`, namespace
> `Mailer\Sdk` → `Recado\Sdk`, classes `Mailer*` → `Recado*` (client, facade,
> service provider, transport, channel, message, headers, exceptions),
> `toMailer()` → `toRecado()`, env vars `MAILER_*` → `RECADO_*`, config
> `mailer-sdk` → `recado-sdk`, transport/channel string `mailer` → `recado`,
> and message headers `X-Mailer-*` → `X-Recado-*`. See the
> [CHANGELOG](CHANGELOG.md) for the full matrix.

### Path repository (monorepo development)

When developing inside the Recado monorepo, point Composer at the package
directory with a **path repository**:

```json
{
    "repositories": [
        { "type": "path", "url": "sdk/php" }
    ],
    "require": {
        "recado/recado-php": "*"
    }
}
```

```bash
composer require recado/recado-php
```

## Quick start: integrate into a Laravel app

The full, copy-pasteable recipe to route a real Laravel app's email through a
Recado instance. (Detailed behavior and options are documented further
down.)

**1. Require the package** (published on Packagist):

```bash
composer require recado/recado-php:^2.0
```

**2. Set the environment variables.**

```dotenv
# Route Laravel's Mail facade through the platform /send API
MAIL_MAILER=recado

# Connection. RECADO_API_TOKEN is REQUIRED (a missing/empty token throws a
# RecadoConfigurationException at boot). RECADO_BASE_URL is OPTIONAL — it
# defaults to the canonical hosted API (https://api.recado.dev/v1; the legacy
# apex path https://recado.dev/api/v1 remains valid); set it only
# if you self-host or point at another environment.
RECADO_API_TOKEN=<your-project-API-key>
# RECADO_BASE_URL=https://your-self-hosted-host/api/v1
```

> **Where to get the API key.** In Recado, open **Settings → API keys** for
> the project you want to send from and create a key (it is shown once — copy it
> straight into `RECADO_API_TOKEN`). Keys are **per project**, so the key also
> selects which project's sender, templates and contacts the sends use.

**3. Add the `recado` mailer to `config/mail.php`.**

```php
'mailers' => [
    // ...existing mailers...
    'recado' => [
        'transport' => 'recado',
    ],
],
```

**4. Send.** Nothing else in your mailing code changes:

```php
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;

// A Mailable through the transport (MAIL_MAILER=recado)
Mail::to('jane@example.com')->send(new WelcomeMail($user));

// A plain message
Mail::raw('Hello world', fn ($m) => $m->to('jane@example.com')->subject('Hi'));
```

Or call the API client directly — e.g. to render a stored template by slug with
per-recipient variables:

```php
use Recado\Sdk\Laravel\Facades\Recado;

Recado::send()->email([
    'to' => 'jane@example.com',
    'template' => 'welcome',           // template slug from Recado
    'variables' => ['first_name' => 'Jane'],
]);

// ...or fully inline content
Recado::send()->email([
    'to' => 'jane@example.com',
    'subject' => 'Welcome aboard',
    'body' => '<p>Hi {{ contact.first_name }}!</p>',
    'text' => 'Hi!',
]);
```

That is the whole integration. The rest of this document covers the SDK's full
surface and the transport's detailed behavior.

## Plain PHP usage

```php
use Recado\Sdk\RecadoClient;

$client = new RecadoClient(
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

// Attachments: max 10 files, 10 MB decoded per file and per send total
// (over-total → 422 with code `attachments_too_large`); executable filename
// extensions are rejected. Single sends only — /send/batch rejects them.
$client->send()->email([
    'to' => 'jane@example.com',
    'template' => 'invoice',
    'attachments' => [
        [
            'filename' => 'invoice.pdf',
            'content_type' => 'application/pdf',
            'content' => base64_encode($pdfBytes), // standard base64, no data: prefix
        ],
    ],
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

> **No attachments in batches.** `/send/batch` rejects `messages.*.attachments`
> with a `422` — attachments are single-send only. Call `send()->email()` per
> recipient instead (the Laravel mail transport does this fan-out for you).

### Notifications (in-app & push)

Send a notification to a contact over one or more channels. Without a `channels`
key the SDK sends `in_app`; pass `channels` to fan out (e.g. in-app **and** push):

```php
$result = $client->notifications()->send([
    'to' => 'jane@example.com',
    'title' => 'Your order shipped',
    'body' => 'Tracking #1234 is on its way.',
    'channels' => ['in_app', 'push'], // optional; defaults to ['in_app']
    'action_url' => 'https://app.example.com/orders/1234',
    'variables' => ['first_name' => 'Jane'],
]);

if (! $result->anyQueued()) {
    // Nothing could be delivered — inspect the per-channel outcomes below.
}

foreach ($result->messages as $channel) {
    // $channel->channel, $channel->id, $channel->status, $channel->errorCode
}

$push = $result->channel('push');
if ($push && ! $push->queued()) {
    echo $push->errorCode; // e.g. push_provider_not_configured
}
```

**Per-channel failures are data, not exceptions.** Each requested channel comes
back as a `NotificationChannelResult`, so one channel failing never aborts the
others — and when *no* channel can be queued (the API replies `422` with the
same envelope) you still get a `NotificationResult` (`anyQueued()` is `false`)
rather than a thrown exception. A *real* validation error (missing `title`, etc.)
still throws `ValidationException`.

| `status`              | `errorCode`                    | Meaning                                  |
|-----------------------|--------------------------------|------------------------------------------|
| `queued`              | —                              | Accepted for delivery on that channel.   |
| `failed_precondition` | `push_provider_not_configured` | Push not set up for the project.         |
| `blocked`             | `recipient_blocked`            | Recipient is suppressed/blocked.         |
| `blocked`             | `quota_exceeded`               | Monthly email/notification quota is out. |
| `blocked`             | `sandbox_cap_exceeded`         | Sandbox project send cap reached.        |

> **Push prerequisites:** the project must have a push provider configured and
> the contact must have at least one registered device token (see below).

### Push device tokens

Register and remove the device tokens push notifications are delivered to. The
contact is upserted with transactional semantics (no double opt-in):

```php
$result = $client->push()->register('jane@example.com', 'fcm-device-token', 'android');
echo $result->registered; // true
echo $result->devices;    // number of push devices now on the contact

$removed = $client->push()->remove('jane@example.com', 'fcm-device-token');
echo $removed->removed;    // true, or false if the contact had no such token
```

The `platform` is the native device platform: `ios` or `android`. This endpoint
registers **native FCM device tokens only** — web push uses a separate VAPID
subscription flow, so `web` is not accepted here (passing it yields a `422`).

Registering a token already owned by another contact in the project **moves** it
to this contact; a contact is capped at **20 devices** (the oldest is evicted
past the cap). Removing a token from an unknown contact raises a
`NotFoundException` (`contact_not_found`).

### Idempotency

`email()` and `batch()` accept a named `idempotencyKey` argument, sent as the
`Idempotency-Key` header. Re-sending with the same key returns the original
result instead of creating a duplicate. While the first request is still in
flight a concurrent retry with the same key gets a `409` — surfaced as a base
`RecadoException` with code `idempotency_conflict`; an empty or over-long key is
rejected with a `422` `ValidationException` (code `invalid_idempotency_key`).

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
$client->contacts()->cursor($query);           // Contact
$client->messages()->cursor($query);           // Message
$client->campaigns()->cursor($query);          // Campaign
$client->lists()->cursor($query);              // ContactList
$client->lists()->contactsCursor($id, $query); // Contact
$client->templates()->cursor($query);          // Template
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

### Resilience (automatic retries)

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
$client = new RecadoClient(
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
middleware yourself via `Recado\Sdk\Http\RetryMiddleware::make()` if you want it.

### Error handling

Every non-2xx response is mapped to a typed exception. All exceptions extend
`Recado\Sdk\Exception\RecadoException` and expose:

- `getErrorCode(): ?string` — the machine `code` field
- `getStatus(): ?int` — the HTTP status
- `getBody(): ?array` — the raw decoded response envelope
- `getMessage(): string` — the human-facing message (standard `\Exception`)

| Exception                 | HTTP status | Notes |
| ------------------------- | ----------- | ----- |
| `AuthenticationException` | 401         | Missing/invalid/expired token. |
| `NotFoundException`       | 404         | e.g. `contact_not_found`, `template_not_found`, `message_not_found`; a sandbox `simulate()` from a **production** token gets a bare `404` (no code). |
| `ValidationException`     | 422         | Validation failures and domain rejections (`recipient_suppressed`, `quota_exceeded`, `template_not_found`, `invalid_status_transition`, sandbox `invalid_event_for_channel` / `link_index_out_of_range`, ...). Adds `errors(): array` (field => messages). |
| `RateLimitException`      | 429         | Adds `retryAfter(): ?int` parsed from the `Retry-After` header. |
| `RecadoConfigurationException` | — (local) | Missing/empty/placeholder base URL (or the decommissioned `mailer.mosaiqo.com` v1.x host) or empty token; thrown at client construction before any request. |
| `UnsupportedFeatureException` | — (local) | The send relies on something the `/send` API has no field for, or that the SDK config disables (e.g. attachments with `recado-sdk.mail.attachments = 'fail'`). |
| `AttachmentsTooLargeException` | — (local) | The decoded attachments of one send exceed the 10 MB total limit; thrown before any upload. `getErrorCode()` is `attachments_too_large`, the same code the server returns for the 422. |
| `RecadoException`         | any other   | Base class; also the catch-all for unexpected non-2xx statuses. |

```php
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Exception\RateLimitException;
use Recado\Sdk\Exception\RecadoException;

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
} catch (RecadoException $e) {
    report($e);
}
```

## Testing with the sandbox

A **sandbox project** lets your CI exercise the real send pipeline without
touching production data or real inboxes. The API token *is* the routing: a
sandbox token quietly captures everything it sends and unlocks the event
simulator; a production token can't even see the simulator (it gets a bare
`404`). So the same code under test only needs a different `RECADO_API_TOKEN`.

The recipe is: **send → read it back from `messages()` (the sandbox inbox) →
simulate an event → assert.**

```php
// 1. Send through the code under test (sandbox token configured).
$client->send()->email(['to' => 'jane@example.com', 'template' => 'welcome']);

// 2. The sandbox captures every send — read it back like an inbox.
$message = $client->messages()->list(['status' => 'queued'])->data[0];

// 3. Drive the pipeline: simulate provider/engagement events on that message.
$client->sandbox()->simulate($message->uuid, SandboxResource::EVENT_DELIVERED);
$client->sandbox()->simulate($message->uuid, SandboxResource::EVENT_OPEN);
$client->sandbox()->simulate($message->uuid, SandboxResource::EVENT_CLICK, linkIndex: 0);

// 4. Assert on the resulting state.
$refreshed = $client->messages()->get($message->uuid);
// $refreshed->events now contains delivered / opened / clicked
```

```php
use Recado\Sdk\Resources\SandboxResource;

// Available events (plain strings work too):
SandboxResource::EVENT_DELIVERED;   // 'delivered'
SandboxResource::EVENT_HARD_BOUNCE; // 'hard_bounce'
SandboxResource::EVENT_SOFT_BOUNCE; // 'soft_bounce'
SandboxResource::EVENT_COMPLAINT;   // 'complaint'
SandboxResource::EVENT_OPEN;        // 'open'
SandboxResource::EVENT_CLICK;       // 'click' — pass linkIndex or url
SandboxResource::EVENT_READ;        // 'read'
```

Notes and safety properties:

- **Webhooks fire for real, flagged `"sandbox": true`.** Outbound webhooks
  triggered by simulated events carry a `"sandbox": true` marker so your
  endpoint can tell test traffic apart.
- **Simulated bounces suppress only inside the sandbox.** A `hard_bounce` /
  `complaint` you simulate adds the address to the *sandbox* project's
  suppression list — it never contaminates production suppression or the shared
  platform reputation.
- **The simulator is invisible to production tokens.** `simulate()` with a
  production token gets a bare `404` (a `NotFoundException` with no error code),
  so a mis-pointed token fails loudly instead of mutating real data.

## Laravel integration

The package is auto-discovered (no manual provider/alias registration). It
registers a container-bound `RecadoClient` singleton, a `Recado` facade, a
`recado` mail transport and a `recado` notification channel — all driven by the
same `recado-sdk` config.

### Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=recado-sdk-config
```

Then set the env vars (every key below maps to `config/recado-sdk.php`):

```dotenv
# Connection. RECADO_API_TOKEN is required; RECADO_BASE_URL is optional and
# defaults to the hosted API (see the connection-config note below).
RECADO_API_TOKEN=your-project-api-key
# RECADO_BASE_URL=https://your-self-hosted-host/api/v1

# HTTP transport / resilience
RECADO_TIMEOUT=10
RECADO_RETRIES=2
RECADO_RETRY_BASE_DELAY=200
RECADO_RETRY_MAX_DELAY=5000

# Mail transport behavior
RECADO_MAIL_ATTACHMENTS=send        # send | fail | ignore
RECADO_MAIL_IDEMPOTENCY=content     # content | random | off
```

Resolve the client from the container:

```php
use Recado\Sdk\RecadoClient;

public function __construct(private RecadoClient $recado) {}

// ...
$this->recado->send()->email([...]);
```

The resilience knobs (`RECADO_TIMEOUT`, `RECADO_RETRIES`,
`RECADO_RETRY_BASE_DELAY`, `RECADO_RETRY_MAX_DELAY`) are wired into the
container-bound client automatically.

> **Connection config.** `RECADO_API_TOKEN` is **required** — an empty token
> throws a `Recado\Sdk\Exception\RecadoConfigurationException` at construction.
> `RECADO_BASE_URL` is **optional**: it defaults to the canonical hosted API
> (`https://api.recado.dev/v1`; the legacy apex path
> `https://recado.dev/api/v1` remains valid), so hosted users only set the
> token; self-hosted users override it. An explicitly empty base URL, the old
> placeholder base URL still throws, instead of silently sending to a dead host.

### Mail transport (`MAIL_MAILER=recado`)

The package registers a `recado` mail driver, so you can route Laravel's `Mail`
facade (and notifications, queued mailers, etc.) through the platform `/send`
API without changing any mailing code.

Add a mailer entry to `config/mail.php`:

```php
'mailers' => [
    // ...
    'recado' => [
        'transport' => 'recado',
    ],
],
```

and select it:

```dotenv
MAIL_MAILER=recado
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

#### Sending with a stored template

To render a platform template by slug instead of inline HTML, set the template
headers on the underlying Symfony message from your Mailable. The transport then
sends a template payload (`{to, template, variables}`) and ignores the inline
subject/body:

```php
use Recado\Sdk\Laravel\Mail\RecadoHeaders;

class WelcomeMail extends Mailable
{
    public function build()
    {
        return $this
            ->subject('Welcome') // ignored when a template header is present
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                $headers->addTextHeader(RecadoHeaders::TEMPLATE, 'welcome');
                $headers->addTextHeader(
                    RecadoHeaders::VARIABLES,
                    json_encode(['first_name' => 'Jane']),
                );
            });
    }
}
```

#### Attachments

Attachments on a Mailable / Symfony message are mapped onto the `/send`
`attachments` field and delivered by the platform. The behavior is driven by
`recado-sdk.mail.attachments` (env `RECADO_MAIL_ATTACHMENTS`):

| Mode | Behavior |
| ---- | -------- |
| `send` (default) | Map each attachment to `{filename, content_type, content(base64)}` and send it. An unnamed attachment gets the filename `attachment` plus an extension inferred from its media type (e.g. `attachment.pdf`). |
| `fail` | Throw `Recado\Sdk\Exception\UnsupportedFeatureException` — the pre-1.4 fail-loud behavior for apps that never want attachments to leave through this transport. |
| `ignore` | Log a warning and send the message *without* the attachments. |

Platform limits (validated server-side, one guard duplicated client-side):

- **Max 10 files per send**, **10 MB decoded per file** and **~10 MB decoded
  total per send**. The SDK checks the *total* before uploading and throws a
  local `Recado\Sdk\Exception\AttachmentsTooLargeException` (error code
  `attachments_too_large` — the same code the server's `422` carries), so an
  oversized send fails fast instead of uploading megabytes of base64 first.
  Per-file size, filename and content-type validation stay server-side.
- **Executable filename extensions are rejected** by the API with a `422` field
  error (`.exe`, `.bat`, `.cmd`, `.com`, `.cpl`, `.dll`, `.jar`, `.js`, `.jse`,
  `.lnk`, `.msi`, `.pif`, `.scr`, `.vbs`, `.vbe`, `.wsf`, `.wsh`, `.ps1`,
  `.msc`, `.hta`, `.reg`), as are filenames with path separators or control
  characters.
- **Batch sends**: the `/send/batch` endpoint rejects attachments (single-send
  only), so a multi-recipient message carrying attachments is automatically
  fanned out as **per-recipient single `/send` calls**. Each fan-out send gets
  its own per-recipient idempotency key (attachments hash into the content
  key), so a requeued job still dedupes; an explicit
  `X-Recado-Idempotency-Key` is derived per recipient
  (`{key}:{sha1(recipient) prefix}`) for the same reason. Expect N API calls
  (and N ratelimit slots) instead of one batch call.

#### Behavior & limitations

The platform `/send` API is intentionally narrow; the transport adapts to it
with explicit, documented behavior rather than silent surprises.

- **From / Reply-To are ignored.** The API does not accept `from` or `reply_to`
  — the platform always uses the project's configured sender (set the project
  `default_from_email`/`default_from_name` and a verified sending domain in the
  dashboard). A `From` set on the message is logged at debug level and dropped.
- **Attachments are sent by default.** They are mapped onto the `/send`
  `attachments` field (see **Attachments** above for modes, limits, the
  filename blocklist and the batch fan-out). Consumers who relied on the old
  fail-loud behavior must set `recado-sdk.mail.attachments = 'fail'`
  explicitly; `'ignore'` still drops them with a warning — never silently.
- **Suppressed recipients are not failures.** When the platform rejects an
  address as suppressed (`recipient_suppressed`), the transport does **not**
  throw: it logs a warning and dispatches a
  `Recado\Sdk\Laravel\Events\MessageSuppressed` event (carrying the recipient
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
  message content is shared, the `to` differs per item). Exception: a message
  carrying attachments fans out as per-recipient single `/send` calls, because
  the batch endpoint rejects attachments (see **Attachments** above).
- **Idempotency is automatic and retry-safe.** Per send the transport sets an
  `Idempotency-Key` derived from `recado-sdk.mail.idempotency` (env
  `RECADO_MAIL_IDEMPOTENCY`):
  - `content` (default): a deterministic hash of the message content, so a
    requeued job never duplicates the send. Two genuinely identical messages
    sent within the platform's idempotency window dedup — switch to `random` if
    that is not what you want.
  - `random`: a fresh UUID per send attempt (no dedup).
  - `off`: no key.
  Override per message with the `X-Recado-Idempotency-Key`
  (`RecadoHeaders::IDEMPOTENCY_KEY`) header.

### Notification channel

The SDK also registers a `recado` **notification channel**, so a Notification
can deliver through the platform `/send` API by returning `['recado']` from
`via()` and defining `toRecado($notifiable)`.

`toRecado()` returns a `Recado\Sdk\Laravel\Mail\RecadoMessage` for full control.
**Inline mode** uses `subject()`/`html()`/`text()`:

```php
use Recado\Sdk\Laravel\Mail\RecadoMessage;

public function toRecado($notifiable): RecadoMessage
{
    return (new RecadoMessage)
        ->subject('Your order shipped')
        ->html('<p>It is on the way.</p>')
        ->text('It is on the way.');
}
```

**Template mode** renders a stored template with per-recipient variables:

```php
return (new RecadoMessage)
    ->template('order-shipped')
    ->variables(['name' => $notifiable->name]);
```

**Recipient routing precedence:** an explicit `RecadoMessage::to()` wins, then
`routeNotificationFor('recado')`, then `routeNotificationFor('mail')`, then a
public `$email` property on the notifiable.

**Other return types** are accepted too: a plain `/send` payload `array` is used
directly (its `to`/`idempotency_key` keys are honored, and an `attachments` key
passes through to the API — see **Attachments** above for shape and limits),
and an `Illuminate\Contracts\Mail\Mailable` is rendered to its subject + HTML
only (its attachments are NOT carried over) — return a `RecadoMessage` for
templates, a text part or an explicit idempotency key, or the array form for
attachments.

The outcome semantics mirror the transport: a **suppressed recipient is not a
failure** — a `Recado\Sdk\Laravel\Events\MessageSuppressed` event is dispatched
and the send is skipped — while quota / sending-domain / any other API error is
rethrown so Laravel marks the notification failed and retries per your queue
policy.

A complete notification:

```php
use Illuminate\Notifications\Notification;
use Recado\Sdk\Laravel\Mail\RecadoMessage;

class OrderShipped extends Notification
{
    public function via($notifiable): array
    {
        return ['recado'];
    }

    public function toRecado($notifiable): RecadoMessage
    {
        return (new RecadoMessage)
            ->subject('Your order shipped')
            ->html('<p>It is on the way.</p>');
        // Or a stored template:
        // return (new RecadoMessage)->template('order-shipped')->variables(['name' => $notifiable->name]);
    }
}
```

### Facade

The package registers a `Recado` facade (auto-registered via package discovery)
that proxies the same container-bound `RecadoClient` singleton — no separate
configuration is needed:

```php
use Recado\Sdk\Laravel\Facades\Recado;

Recado::contacts()->list();
Recado::send()->email([
    'to' => 'jane@example.com',
    'subject' => 'Hello',
    'body' => '<p>Hi</p>',
]);
```

## Contributing / Development

```bash
composer install
composer test        # alias for: vendor/bin/phpunit
```

Tests run entirely against a Guzzle `MockHandler` — no network access required.

For how this package is split out of the monorepo into its own repository,
tagged with SemVer and published to Packagist, see
[`PUBLISHING.md`](PUBLISHING.md).
