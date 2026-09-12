# Recado PHP SDK

Official PHP SDK for the **Recado** REST API v1. It wraps the transactional
send, contacts, lists, segments, tags, templates, messages, campaigns,
broadcasts, waitlists, webhook endpoints, events, sending and custom domains and
email verification behind typed resources and readonly DTOs, with first-class
error handling and idempotency support — plus an optional, batteries-included
Laravel integration.

> ⚠️ **Read-only mirror.** This repo is an automated split of the SDK from a
> private monorepo. **Do not open pull requests here** — they can't be merged
> (the mirror is force-pushed) and will be auto-closed. Found a bug or have a
> request? **Open an issue** (see [CONTRIBUTING](CONTRIBUTING.md)).

> 🤖 **Integrating with an AI agent?** Start with [AGENTS.md](AGENTS.md) — a
> terse, imperative integration playbook with the human-gate steps called out.
> This README is the full human reference.

## Features

- Typed resources + readonly DTOs over the full Recado API v1 surface (send,
  contacts, imports, lists, segments, tags, templates, notification templates,
  messages, campaigns, broadcasts, waitlists, webhook endpoints, events,
  sending and custom domains, email verification, the project profile, delivery
  health and notification analytics).
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

#### Notification templates

Instead of inline `title`/`body`, pass a **`template`** slug (a notification
template managed in the dashboard under Transactional → Notification
templates) plus optional `variables` — the two content modes are mutually
exclusive:

```php
$result = $client->notifications()->send([
    'to' => 'jane@example.com',
    'template' => 'order-shipped',
    'variables' => ['order_id' => 'ord_8842'],
    'action_url' => 'https://app.example.com/orders/1234', // optional override
]);
```

The API resolves the template's **locale variants** per recipient (contact
locale — exact tag, then language prefix — then the project default locale,
then the base content) and snapshots the resolved content on the queued
message, so deleting the template never breaks in-flight sends. The
template's `action_url`/`icon` act as defaults that a per-send value
overrides. An unknown slug throws a `ValidationException` with code
`template_not_found` (like `send()->email()`). Batch items accept the same
`template` field; there an unknown slug is a **per-item, per-channel
outcome** (`status` `failed_precondition`, `errorCode` `template_not_found`)
that never aborts the batch.

#### Batch notifications

Send up to **100** notifications in one request (rate limited to **10
requests/min** per token). Each item takes the same fields as `send()`, and an
item without `channels` defaults to `['in_app']`:

```php
$result = $client->notifications()->batch([
    ['to' => 'jane@example.com', 'title' => 'Shipped', 'body' => 'On its way.'],
    [
        'to' => 'john@example.com',
        'title' => 'Shipped',
        'body' => 'On its way.',
        'channels' => ['in_app', 'push'],
    ],
], idempotencyKey: 'orders-2026-08-15'); // optional

echo $result->queued; // channel dispatches accepted
echo $result->failed; // channel dispatches that could not be queued

foreach ($result->messages as $item) {
    // $item->index, $item->to, $item->anyQueued()
    $push = $item->channel('push');
    if ($push && ! $push->queued()) {
        echo $push->errorCode; // e.g. upgrade_required
    }
}
```

`queued` and `failed` count **channel dispatches, not items**: a two-channel
item whose push failed contributes to both. Unlike `send()`, the batch endpoint
always answers `202` — even when nothing at all could be queued — because a
batch has no single meaningful outcome; the per-channel error codes are the
same ones listed above (plus `upgrade_required` when the plan does not include
push). A single malformed item rejects the **whole** request with a
`ValidationException` keyed by index (e.g. `messages.2.title`).

Idempotency keys work at the batch level (1–255 chars, 24h replay) and live in
their own namespace, so a `/send/batch` key with the same string is unrelated.
A retry arriving while the first request is still in flight throws a
`RecadoException` with status `409` and code `idempotency_conflict`; a batch
that queued nothing does not consume its key.

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

// ...and set contact fields on the contact the event upserts (4th argument,
// optional): `first_name`, `last_name`, `name` and `locale`. Use `name` when
// you only have the full name — the API splits it on the first whitespace, and
// explicit `first_name`/`last_name` always win. Every field is set on create,
// updated when provided, and never cleared when omitted.
$client->send()->track('signed-up', 'jane@example.com', [], [
    'first_name' => 'Jane',
    'last_name' => 'Doe',
    'locale' => 'es-MX',
]);
$client->send()->track('signed-up', 'ada@example.com', [], ['name' => 'Ada Lovelace']);

// The same array also takes `lists` (ids of your lists, max 50) and `tags`
// (names, max 25, created on first use). The API attaches them BEFORE it
// records the occurrence, so an event-triggered automation already sees them
// (a `has_tag` condition can match a tag sent with this very call). Both are
// idempotent and neither changes the contact's subscription status; an unknown
// list id throws a `ValidationException` with the code `list_not_found`.
$client->send()->track('signed-up', 'ada@example.com', [], [
    'name' => 'Ada Lovelace',
    'lists' => [1],
    'tags' => ['beta'],
]);

// The positional arguments always win: an `email`/`event`/`data` key in the
// contact array is ignored, never a way to redirect the call.

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

// Bulk attribute upsert: up to 500 contacts per request, own 30/min limiter.
// Update-only (unknown emails come back as `skipped_not_found`, never created)
// and consent-safe (status, subscribed_at/unsubscribed_at and list memberships
// are never written). Attributes MERGE, so absent keys survive.
$result = $client->contacts()->batchUpdate([
    [
        'email' => 'jane@example.com',
        'attributes' => ['plan' => 'pro', 'last_active_at' => '2026-09-10'],
        'tags_add' => ['power-user'],
        'tags_remove' => ['trial'],
    ],
], idempotencyKey: 'attribute-refresh-2026-09-10');
// $result['results'][0]['status'] === 'updated' | 'skipped_not_found' | 'invalid_attributes'

$client->contacts()->tags('jane@example.com', add: ['vip'], remove: ['trial']);
$client->contacts()->cancelAutomationRuns('jane@example.com', automation: 12);
$client->contacts()->delete('jane@example.com'); // GDPR erase

// Lists
$lists = $client->lists()->list();
$list = $client->lists()->create('Newsletter', 'Weekly digest');
$client->lists()->attachContact($list->id, 'jane@example.com');
$client->lists()->detachContact($list->id, 'jane@example.com');
$client->lists()->update($list->id, ['name' => 'Weekly digest']); // membership untouched
$client->lists()->delete($list->id);                              // contacts survive

// Tags (flat array) — full CRUD, including the preference-center fields
$tags = $client->tags()->list();
// create() is create-or-FIND: an existing name (matched case-insensitively)
// comes back unchanged, so it never repaints a tag someone already curated.
$tag = $client->tags()->create('vip', [
    'color' => '#16a34a',
    'is_public' => true,                  // shows as an opt-in checkbox on the
    'public_label' => 'Insider news',     // preference center, labelled by
    'public_description' => 'Occasional early access.',
]);
$client->tags()->update($tag->id, ['public_label' => 'Insiders']);
$client->tags()->delete($tag->id); // detached from every contact; contacts survive

// Bulk contact imports — the only surface that creates contacts in bulk WITH
// their consent state. Asynchronous: poll until the run is finished.
$import = $client->imports()->create([
    ['email' => 'ada@example.com', 'first_name' => 'Ada', 'tags' => ['vip']],
    ['email' => 'grace@example.com', 'status' => 'unsubscribed'],
], ['lists' => [12], 'tags' => ['migration-2026']]);

$import = $client->imports()->get($import->id);
$import->isFinished(); // status is completed or failed
$import->invalidStatusRows; // a warning: the row imported, its status did not parse

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

// Notification templates (the in-app/push sibling — addressed by slug, which is
// what notifications()->send() accepts as `template`)
$client->notificationTemplates()->create([
    'name' => 'Order shipped',
    'slug' => 'order-shipped',
    'title' => 'On its way, {{ contact.first_name }}',
    'body' => 'Your order left the warehouse.',
    'action_url' => 'myapp://orders/42', // deep links are allowed, scripts never
]);
// A variant is a FULL replace: omitting action_url RESETS it for that locale.
$client->notificationTemplates()->putVariant('order-shipped', 'es-MX', [
    'title' => 'En camino',
    'body' => 'Tu pedido salió del almacén.',
]);
$client->notificationTemplates()->delete('order-shipped');

// Messages
$messages = $client->messages()->list(['status' => 'delivered']);
$message = $client->messages()->get('11111111-2222-...');
foreach ($message->events as $event) {
    // $event->type, $event->payload, $event->occurredAt
}
// Resend: queues a BRAND-NEW message with the original's recipient and rendered
// content. Suppression, quota and warm-up all re-run, so the copy can still be
// refused (`message_not_resendable`, `recipient_suppressed`, `quota_exceeded`).
$copy = $client->messages()->resend($message->uuid);

// Campaigns (full lifecycle — see "Campaigns" below)
$campaigns = $client->campaigns()->list(['status' => 'sent', 'per_page' => 50]);
$campaign = $client->campaigns()->get(7);
// $campaign->stats is a populated CampaignStats on get() (and on
// list(['include' => 'stats'])):
echo $campaign->stats->openRate ?? 0; // rates are null when undefined

// Segments (the saved queries campaigns target)
$segments = $client->segments()->list();
$segment = $client->segments()->create('Active EU subscribers', [
    'match' => 'all',
    'conditions' => [
        ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
        ['field' => 'email', 'operator' => 'ends_with', 'value' => '.eu'],
    ],
]);
$client->segments()->get($segment->id)->contactsCount; // live count
$client->segments()->update($segment->id, ['name' => 'EU subscribers']);
$client->segments()->delete($segment->id);

// Webhook endpoints (the secret is returned once, by create())
use Recado\Sdk\Webhooks\WebhookEvent;

$endpoint = $client->webhooks()->create('https://hooks.example.com/recado', [
    WebhookEvent::CampaignStarted,
    WebhookEvent::CampaignSent,
    'contact.unsubscribed', // plain strings work too
]);
$endpoint->secret; // store it now — no other endpoint ever returns it
$client->webhooks()->update($endpoint->id, ['enabled' => false]);
// toggle() is how you RECOVER an endpoint that auto-disabled after 10 failed
// deliveries: enabling it also resets consecutive_failures and disabled_at.
$client->webhooks()->toggle($endpoint->id, true);
// ping() answers "queued", not "delivered" — read the outcome back from the log.
$client->webhooks()->ping($endpoint->id);
foreach ($client->webhooks()->deliveriesCursor($endpoint->id) as $attempt) {
    // $attempt->event, $attempt->status (null = no response), $attempt->success
}
$client->webhooks()->delete($endpoint->id);

// Delivery health (read-only; production projects only — a sandbox token is
// refused with the code `not_available_in_sandbox`, see isNotAvailableInSandbox())
$health = $client->delivery()->health();
foreach ($health->pausedDomains() as $domain) {
    // The reputation breaker is holding this identity. Resuming it is a HUMAN
    // action in the dashboard; there is no API for it.
    $domain->domain; $domain->breaker['tripped_at'];
}
$health->suppressions->global; // THIS project's contacts on the shared list

// Notification analytics (works in a sandbox too — intercepted sends are
// recorded, so this is how you read back a test run)
$analytics = $client->notifications()->analytics();
$analytics->channel('push')?->openRate; // null on a zero denominator
$analytics->registry->activeTotal;

// Events (the read side of track())
foreach ($client->events()->cursor(['event' => 'order.completed']) as $occurrence) {
    // $occurrence->eventName, $occurrence->contactEmail, $occurrence->data
}
$client->events()->forContact('jane@example.com', ['since' => '2026-09-01T00:00:00Z']);

// Delete an event from the catalogue. IRREVERSIBLE, and it takes the whole
// occurrence log with it (plus any automation EXIT rule on the event). An
// automation TRIGGERED by it survives and heals the next time you track the
// same name. A name containing a "/" is refused locally — delete it from the
// dashboard or over MCP.
$client->events()->delete('user.registered');

// Who is this key? Most usefully: is it a sandbox credential?
$profile = $client->project()->get();
$profile->isSandbox();          // tell a test key from a production one
$profile->defaultFromEmail;     // what a send inherits when it sets no `from`
$profile->publicUrl;            // where archive/unsubscribe/webview links live

// Dry-run a segment definition without creating anything
$preview = $client->segments()->preview([
    'match' => 'all',
    'conditions' => [
        ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
        ['field' => 'tags', 'operator' => 'has', 'value' => 'vip'],
    ],
], sampleSize: 5);
$preview->contactsCount;  // 320
$preview->sample;         // up to 5 Contact DTOs, newest first (0 = count only)

// Clean a list: drop the members that can no longer be emailed. MEMBERSHIP
// ONLY — contacts are never deleted, never change status, keep their other
// lists, and no event or outbound webhook fires.
$result = $client->lists()->clean(12);                       // all three statuses
$result = $client->lists()->clean(12, ['bounced'], true);    // + invalid addresses
if ($result->queued) {
    // Above the platform's inline threshold the work is queued: `removed` is
    // null and `matching` is what the job will work through.
    $result->matching;
}
```

Paginated endpoints return a `Paginated` DTO exposing `->data` (mapped DTOs),
`->meta` and `->links`. The tags and webhooks listings return flat arrays.

### Campaigns

The campaigns resource covers the whole newsletter lifecycle: create and edit a
draft, inspect it before sending, then send, schedule, cancel, duplicate or
delete it.

```php
$campaign = $client->campaigns()->create([
    'name' => 'July product update',
    'subject' => 'What shipped in July',
    'editor' => 'markdown', // blocks (default) | html | markdown — immutable
    'content' => ['source' => '# Hi {{ contact.first_name }}'],
    'lists' => [3],
    'segments' => [7],
]);

$client->campaigns()->update($campaign->id, ['subject' => 'What actually shipped']);

// Look before you leap: how many people, what would fail, what it looks like.
$client->campaigns()->recipientCount(lists: [3], segments: [7]); // int
$readiness = $client->campaigns()->readiness($campaign->id);
foreach ($readiness->failures() as $check) {
    echo $check->key.': '.$check->code.PHP_EOL; // e.g. quota: quota_exceeded
}
$preview = $client->campaigns()->preview($campaign->id, contactEmail: 'jane@example.com');
$client->campaigns()->testSend($campaign->id, ['me@example.com']);
```

#### A/B testing

Pass `ab_test` and `variants` to the same `create()` / `update()` calls — the
whole test is authored in one request, no dashboard round trip:

```php
$campaign = $client->campaigns()->create([
    'name' => 'July product update',
    'editor' => 'markdown',
    'subject' => 'What shipped in July',       // the base every variant inherits
    'content' => ['source' => '# Hi {{ contact.first_name }}'],
    'lists' => [3],
    'ab_test' => [
        'enabled' => true,
        'test_fraction' => 0.2,        // 0.1..0.5 of the audience (default 0.2)
        'winner_metric' => 'opens',    // opens | clicks       (default opens)
        'test_duration_minutes' => 240, // 30..2880            (default 240)
    ],
    'variants' => [
        ['subject' => 'What shipped in July'],   // inherits the campaign body
        ['subject' => 'July: 11 new things', 'content' => ['source' => '# Eleven']],
    ],
]);

$campaign->abTest->variants[0]->label; // 'A' — labels are server-assigned
```

Every variant field (`subject`, `preheader`, `from_name`, `from_email`,
`content`) is optional and a null one inherits the campaign's own; the variant
content always uses the campaign's editor shape, since a variant never has an
editor of its own. `variants` replaces the whole set on each write, so editing
or removing one means sending the set you want; `['enabled' => false]` turns the
test off and clears them.

The authored test comes back as `$campaign->abTest` on every read and write
(no `include` needed). Once the test group has gone out it is frozen:

```php
if ($campaign->abTest?->locked) {
    // ab_test_state is testing/deciding/finished — a write touching the
    // variants now is a 422 with code ab_test_locked.
}
```

Two variants are required to SEND (not to save a half-built draft) — the
`ab_variants` readiness check reports `ab_invalid_variants`, or
`missing_subject` / `missing_content` with the offending label in its meta.
Enabling the test needs the A/B plan feature; without it the write is a 422
with `ab_test_requires_plan`.

**Sending requires an explicit confirmation.** `send()` fires real mail at a
real audience and cannot be recalled once the batch is queued, so the intent has
to be spelled out at the call site:

```php
use Recado\Sdk\Exception\CampaignSendNotConfirmedException;

$client->campaigns()->send($campaign->id, confirm: true); // sends

$client->campaigns()->send($campaign->id); // throws CampaignSendNotConfirmedException
```

Without `confirm: true` the exception is thrown **before any HTTP request is
made** — a stray or accidental `send()` never reaches the API, let alone the
audience. (This replaces the old posture, where the resource simply had no write
methods at all.) Nothing else needs confirming: `schedule()` only arms a future
send that `unschedule()` or `cancel()` can still stop.

```php
$client->campaigns()->schedule($campaign->id, '2026-07-05T09:00:00+00:00');
$client->campaigns()->unschedule($campaign->id);   // back to draft
$client->campaigns()->cancel($campaign->id);       // scheduled/sending → cancelled
$copy = $client->campaigns()->duplicate($campaign->id, 'Week 38'); // fresh draft
$client->campaigns()->delete($campaign->id);       // draft/cancelled/failed only
```

Reporting:

```php
// Filters, sorting and embedded stats for a table view.
$page = $client->campaigns()->list([
    'status' => ['sent', 'failed'],
    'search' => 'July',
    'sort' => '-scheduled_at',
    'include' => 'stats',
]);

// Refresh the metrics of rows you already hold (one aggregate query).
$stats = $client->campaigns()->stats([31, 32]); // [31 => CampaignStats, ...]

// Click and A/B reporting on the detail endpoint.
$campaign = $client->campaigns()->get(31, ['include' => 'top_links,variants']);
$campaign->topLinks[0]->url;
$campaign->variants[0]->isWinner;

// Publication flags (drafts only, like every campaign field):
//  - in_archive (default true): listed on the project's public archive page
//    and RSS feed once the campaign has been sent.
//  - premium (default false): goes to paid subscribers only. Turning it ON
//    requires the project's monetization to be enabled, otherwise the write is
//    refused with `premium_monetization_disabled` and NOTHING is written.
//    Turning it off is always allowed.
$client->campaigns()->update(31, ['in_archive' => false, 'premium' => true]);
$campaign->inArchive;
$campaign->premium;
```

Failures keep the API's machine codes on the typed exceptions, untranslated —
`$e->getErrorCode()` returns `not_sendable`, `missing_subject`,
`missing_content`, `no_recipients`, `sending_domain_not_verified`,
`quota_exceeded`, `ab_invalid_variants`, `campaign_not_editable`,
`campaign_not_cancellable`, `campaign_not_deletable`,
`premium_monetization_disabled`.

### Broadcasts

A **broadcast** is a mass in-app and/or push notification send — the
notification sibling of a campaign. Email is deliberately not a broadcast
channel: mass email is what campaigns are for. The lifecycle mirrors campaigns
field for field, so code that drives one drives the other.

```php
// Size the audience per channel BEFORE creating anything. Every sendable
// channel is counted, not only the ones a broadcast selects.
$counts = $client->broadcasts()->recipientCount(lists: [3], segments: [7]);
$counts->for('push');        // 612
$counts->recipientsTotal;    // the SUM: a contact reachable twice is two sends

$broadcast = $client->broadcasts()->create([
    'name' => 'Launch day',                 // internal only
    'title' => 'We launched',
    'body' => 'The new dashboard is live.',
    'action_url' => 'myapp://dashboard',    // deep links allowed, scripts never
    'channels' => ['in_app', 'push'],       // `email` is rejected
    'lists' => [3],
    'segments' => [7],
]);

// Title, body and channels may stay empty while drafting; they are enforced at
// send time, exactly like a campaign's subject and content.
$client->broadcasts()->update($broadcast->id, ['channels' => ['push']]);

// A proof to ONE existing contact of the project (an unknown address could only
// produce a blocked send). It carries no broadcast id, so stats stay clean.
$client->broadcasts()->testSend($broadcast->id, 'reader@example.com');

// Sending needs the same explicit confirmation a campaign send does: without
// `confirm: true` this throws CampaignSendNotConfirmedException locally and no
// request is made.
$client->broadcasts()->send($broadcast->id, confirm: true);

$client->broadcasts()->schedule($broadcast->id, '2026-12-01T09:00:00Z');
$client->broadcasts()->unschedule($broadcast->id); // back to draft
$client->broadcasts()->cancel($broadcast->id);     // ends `cancelled`, not draft

$client->broadcasts()->get($broadcast->id)->stats?->openRate; // null on a zero denominator
```

Failures keep their machine codes: `not_sendable`, `missing_content`,
`no_channels`, `no_recipients`, `push_not_entitled`, `push_not_configured`,
`quota_exceeded`, `broadcast_not_editable`, `broadcast_not_scheduled`,
`broadcast_not_cancellable`, `recipient_blocked`.

### Waitlists

A **waitlist** is a hosted pre-launch signup page (`/w/{slug}`) with referral
positions. The SDK covers the read side plus the one irreversible action:
authoring stays in the dashboard, because creating a waitlist also creates its
dedicated contact list and claims a globally unique public slug.

```php
foreach ($client->waitlists()->cursor(['status' => 'open', 'include' => 'stats']) as $waitlist) {
    $waitlist->publicUrl;        // on the project's canonical public host
    $waitlist->membersCount;     // total signups ever recorded
    $waitlist->stats?->last7Days; // null unless you asked for include=stats
}

// Members come back in RANK order with a LIVE position. Positions are never
// stored, and the `email` filter is applied AFTER ranking — a matched member
// still reports the position it holds on the full list.
foreach ($client->waitlists()->membersCursor(7) as $member) {
    $member->position;        // 1-based
    $member->referralCode;    // the code in their own share link (not a secret)
    $member->referralsCount;  // credited referrals, never decremented
}
```

Launching is **irreversible** — there is no unlaunch, here or in the dashboard:

```php
$waitlist = $client->waitlists()->launch(7);

// It claims open → launched atomically, stops the public signup page, tags
// every member contact `early-adopter` through the normal tag path (so
// `tag_added` automations fire), and creates a DRAFT announcement campaign
// targeted at the waitlist's own list:
$waitlist->launchCampaignId;

// Pass false to skip that campaign:
$client->waitlists()->launch(7, createCampaign: false);
```

A second launch (and the loser of a concurrent one) throws a
`ValidationException` with the code `waitlist_already_launched`; an unknown or
cross-project id is a `NotFoundException` with `waitlist_not_found`.

Permissions reuse the contacts pair: `contacts.view` to read, `contacts.manage`
to launch.

### Sending domains

The automated-onboarding loop: add the domain, read back the DNS records to
publish, pipe them into your DNS provider, poll, clean up — without anyone
opening the dashboard.

```php
$domain = $client->sendingDomains()->add('mail.example.com');

foreach ($domain->records as $record) {
    // Shaped for a DNS panel: `host` relative to the zone, `fqdn` alongside,
    // an MX `priority` split into its own field, a concrete `ttl` suggestion.
    $record->type; $record->host; $record->fqdn; $record->value; $record->priority;
}

// `dmarc` is a RECOMMENDATION, never a requirement — sending works without it.
$domain->dmarc?->value;

// Poll until the provider itself sees your records.
$domain = $client->sendingDomains()->check($domain->id);
$domain->isVerified();
```

`state` is the field worth polling, because it blends the provider's truth with
Recado's **own** live DNS lookups:

| `state` | Meaning |
| --- | --- |
| `verified` | The provider confirms it. The only source of truth for sending. |
| `detected` | Recado's resolver already sees the value, the provider is still pending. Never promoted to `verified` on that detection alone. |
| `not_found` | Not published yet. The only "your turn" state — `missingRecords()` returns exactly these. |
| `failed` | The provider reports failure. |

```php
foreach ($domain->missingRecords() as $record) {
    // Still on your side to publish.
}

$client->sendingDomains()->list();     // every identity, alphabetically
$client->sendingDomains()->get(12);
$client->sendingDomains()->delete(12); // also deletes the provider identity
```

Every representation performs live DNS lookups, so treat these calls as a status
poll, not a hot path.

Refusals keep their machine codes: `verification_unsupported` (the active
provider has no domain API — generic SMTP, or Postmark without the optional
account token — so the row could never leave `pending`), `already_added`,
`provider_error` and `verification_failed` (the provider could not be reached;
the stored status is untouched and the call is worth retrying).

`SendingDomain::$warmup` mirrors the dashboard's ramp block for a warming
identity. **Skipping a ramp and resuming a breaker-paused identity have no API
and the SDK fakes none**: they are judgement calls about sending reputation and
stay in the dashboard, where the trip rates sit in front of a human.

Not available in a sandbox — see [Production-only
endpoints](#production-only-endpoints) below.

### Custom domains

The project's own public hostname (`news.customer.com`). Once verified, public
pages, webviews, tracking and unsubscribe links are served on it; until then
(and if it later breaks) everything falls back to the platform subdomain, so
links in already-sent mail keep working.

**One domain per project.** The collection is a collection for forward
compatibility; today it holds 0 or 1 element, and `current()` collapses that:

```php
$domain = $client->customDomains()->add('news.acme.com');
$domain->record->type;   // CNAME
$domain->record->value;  // the platform subdomain to point at

$domain = $client->customDomains()->check($domain->id);
$domain->isVerified();
$domain->tlsPending;     // DNS ok, certificate still on its way
$domain->tlsError;       // rate_limited | rejected | api_error | certificate_failed | timeout | exhausted

$client->customDomains()->current();  // ?CustomDomain
$client->customDomains()->delete($domain->id);
```

Verification has two gates, both run by `check()`: the CNAME ownership proof
(A-records deliberately do not verify — failure is `cname_missing`) and a
TLS-live probe of `https://{hostname}/up`. `verificationError` and `tlsError`
stay **machine codes**: an integration branches on them, and a localized
sentence is not a contract.

Adding requires the `custom_domains` plan entitlement (`422`
`custom_domains_not_entitled`); checking and deleting stay ungated, so a
downgraded team keeps managing the domain it already has. Other refusals:
`custom_domain_limit_reached`, and `check_throttled` (one check per domain per
15 minutes, with `retry_after` seconds on the response body).

Not available in a sandbox — see [Production-only
endpoints](#production-only-endpoints) below.

### Email verification (billed)

Every contact already carries Recado's **free** verdict
(`Contact::$verificationStatus`: `valid`, `risky`, `invalid`, `unknown`),
computed in-process with no external provider. The API only marks, it never
rejects — the one place the verdict changes behaviour is campaign audiences,
where `invalid` contacts are excluded like suppressed ones.

On top of that, a project can connect its **own** ZeroBounce or Kickbox account
and ask for mailbox-level verification. Those lookups are billed per address to
that account and **there is no refund**, so the SDK keeps the cost gate
first-class: `run()` takes the estimate **object**, never a bare number.

```php
$estimate = $client->verification()->estimate(listId: 12);

$estimate->contactsInScope;   // 1840
$estimate->addresses;         // 1204 — what you would actually pay for
$estimate->creditsRemaining;  // null when the provider reports no balance
$estimate->sufficientCredits;

$run = $client->verification()->run($estimate);

while (! $run->isFinished()) {
    sleep(5);
    $run = $client->verification()->get($run->id);
}

$run->updated;  // addresses the provider answered for
$run->failed;   // lookups that could not be made — a provider outage never
                // rewrites a stored verdict
```

`addresses` is smaller than `contactsInScope` whenever addresses already carry
an external verdict newer than the re-verify window (30 days by default): those
are skipped and cost nothing, which is what makes re-running the same list
cheap. Omit both arguments to scope the estimate to the whole audience;
`contactIds` is capped at 5000.

If the audience moved between the estimate and the run, the platform refuses to
spend on a figure nobody has seen — and hands you the current one:

```php
use Recado\Sdk\Exception\VerificationEstimateMismatchException;

try {
    $run = $client->verification()->run($estimate);
} catch (VerificationEstimateMismatchException $e) {
    $fresh = $e->currentEstimate();          // same scope, current numbers
    $run = $client->verification()->run($fresh);
}
```

It extends `ValidationException`, so code that only catches that keeps working.
Other refusals: `verification_not_configured` (no provider, or one switched off
— a disabled provider must never spend credits), `409`
`verification_already_running` (one run per project at a time), `list_not_found`
and `verification_run_not_found`. The gate applies in a sandbox too.

### Production-only endpoints

Sending domains, custom domains and delivery health are refused for a **sandbox**
credential: a sandbox never sends externally and never serves customer-facing
pages, so it has no sending identities, no public hostname and no sending
reputation — and its token must not reach the production project's.

Branch on the **code**, not the status. The platform is unifying those refusals
on `404` (they used to be a mix of `404` and `422`), so the SDK exposes the check
on the exception base class:

```php
use Recado\Sdk\Exception\RecadoException;

try {
    $client->sendingDomains()->list();
} catch (RecadoException $e) {
    if ($e->isNotAvailableInSandbox()) {
        // This credential is a sandbox key. Use the production one.
    }
}
```

`$client->project()->get()->isSandbox()` answers the same question up front,
without provoking a refusal.

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
$client->segments()->cursor($query);           // Segment
$client->events()->cursor($query);             // EventOccurrence
$client->events()->forContactCursor($email);   // EventOccurrence
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
- `isNotAvailableInSandbox(): bool` — the production-only refusal, matched on the
  code rather than the status (see [Production-only
  endpoints](#production-only-endpoints))

| Exception                 | HTTP status | Notes |
| ------------------------- | ----------- | ----- |
| `AuthenticationException` | 401         | Missing/invalid/expired token. |
| `NotFoundException`       | 404         | e.g. `contact_not_found`, `template_not_found`, `message_not_found`; a sandbox `simulate()` from a **production** token gets a bare `404` (no code). |
| `ValidationException`     | 422         | Validation failures and domain rejections (`recipient_suppressed`, `quota_exceeded`, `template_not_found`, `invalid_status_transition`, sandbox `invalid_event_for_channel` / `link_index_out_of_range`, ...). Adds `errors(): array` (field => messages). |
| `VerificationEstimateMismatchException` | 422 | Subclass of `ValidationException` for `estimate_mismatch`: a billed verification run was refused because the scope moved. Adds `currentEstimate(): ?VerificationEstimate`, ready to hand back to `run()`. |
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
RECADO_MAIL_FORWARD_FROM=true       # forward the message From/Reply-To
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

- **From / Reply-To are forwarded.** A message that sets its own sender
  (`->from('alex@example.com', 'Alex')`, `->replyTo(...)`) is sent with that
  address: the transport maps it onto the `/send` `from`, `from_name` and
  `reply_to` fields, on single sends and on batch items alike. A message that
  sets neither is unchanged — the project's configured sender
  (`default_from_email`/`default_from_name`) applies as before. The API takes
  one address per field, so extra From/Reply-To addresses are dropped with a
  debug log.
  **The from domain must be a verified sending domain of the project**: the
  platform rejects anything else with `422 sending_domain_not_verified`, which
  the transport re-throws as a `TransportException` naming the refused address
  and domain and how to fix it:
  > Recado rejected the sender "alex@example.com": the domain "example.com" is
  > not a verified sending domain of this project. Verify it under Settings →
  > Sending domains, or remove the From override (set
  > `RECADO_MAIL_FORWARD_FROM=false` to fall back to the project default
  > sender).

  Add and verify each sender's domain under *Sending domains* in the
  dashboard. Set `recado-sdk.mail.forward_from` to `false` (env
  `RECADO_MAIL_FORWARD_FROM=false`) to restore the old behavior and always send
  as the project's configured sender.
- **The recipient's display name is forwarded.** `->to(new Address('ada@example.com',
  'Ada Lovelace'))` sends `name: "Ada Lovelace"` on the `/send` payload, so the
  platform fills the first/last name of the contact it creates or updates. The
  name is resolved **per recipient** (the To/Cc/Bcc address matching that
  recipient), on single sends and batch items alike — a batch never labels
  everyone with the first To's name. A recipient without a display name adds
  nothing to the payload, and the name only joins the content idempotency key
  when it is actually present, so sends without display names keep the exact
  key they had before.
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
composer lint        # Pint (fix)   — alias for: vendor/bin/pint
composer lint:check  # Pint (check) — alias for: vendor/bin/pint --test
composer test        # alias for: vendor/bin/phpunit
```

Tests run entirely against a Guzzle `MockHandler` — no network access required.

Both commands run in CI (`.github/workflows/sdk.yml` in the source monorepo) on
PHP 8.4 and 8.5 for every push and pull request, so a change that breaks the
suite or the code style cannot land.

For how this package is split out of the monorepo into its own repository,
tagged with SemVer and published to Packagist, see
[`PUBLISHING.md`](PUBLISHING.md).
