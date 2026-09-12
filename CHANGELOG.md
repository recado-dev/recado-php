# Changelog

All notable changes to `recado/recado-php` (formerly `mosaiqo/mailer-php`,
through v1.4.0) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Lists write surface.** `ListsResource::update()` (PATCH `/lists/{id}` —
  membership untouched) and `delete()` (the membership rows go, the contacts
  survive).
- **Tags CRUD.** `TagsResource::create()` (create-or-**find** by name, matched
  case-insensitively: an existing tag comes back unchanged), `update()` and
  `delete()`. The `Tag` DTO gained the preference-center trio `isPublic`,
  `publicLabel`, `publicDescription` plus `createdAt`, so a tag can be published
  as an opt-in checkbox on the project's preference center.
- **`BroadcastsResource`** — the whole `/broadcasts` lifecycle (list, cursor,
  get, create, update, delete, send, schedule, unschedule, cancel, testSend,
  recipientCount) with the `Broadcast`, `BroadcastStats` and
  `BroadcastRecipientCounts` DTOs. A broadcast is a mass in-app/push send, the
  notification sibling of a campaign. `send($id, confirm: true)` carries the
  SAME local guard as a campaign send: without the confirmation it throws
  `CampaignSendNotConfirmedException` before any HTTP request is made (the
  exception gained a `forBroadcast()` factory so one type still covers both).
- **Webhook operations.** `WebhooksResource::toggle()` (PUT
  `/webhooks/{id}/toggle` — enabling a disabled endpoint also resets
  `consecutive_failures` and clears `disabled_at`, which is how you recover one
  that auto-disabled), `ping()` (answers QUEUED, not delivered) and
  `deliveries()` / `deliveriesCursor()` over the new `WebhookDelivery` DTO. A
  delivery with no response at all reports `status: null`, never a `0`.
- **`NotificationTemplatesResource`** — CRUD plus per-locale variants
  (`putVariant()` / `deleteVariant()`) with the `NotificationTemplate` and
  `NotificationTemplateVariant` DTOs. A variant is a FULL replace: an omitted
  `action_url`/`icon` is reset for that locale, not inherited from the base.
- **`MessagesResource::resend()`** — POST `/messages/{uuid}/resend`, which
  queues a BRAND-NEW message with the original's recipient and rendered content.
  Suppression, quota/sandbox cap and warm-up all re-run, so it keeps the machine
  codes `message_not_resendable`, `contact_not_found`,
  `sending_domain_not_verified`, `recipient_suppressed`, `quota_exceeded` and
  `sandbox_cap_exceeded`.
- **Campaign publication flags.** `Campaign::$inArchive` and `$premium`, and the
  matching `in_archive` / `premium` keys on `campaigns()->create()` and
  `update()`. Turning premium ON requires the project's monetization to be
  enabled — otherwise the write is refused with the new error code
  `premium_monetization_disabled` and nothing is written; turning it off is
  always allowed.
- **`DeliveryResource::health()`** — GET `/delivery/health` with the
  `DeliveryHealth`, `SendingDomainHealth`, `SuppressionPressure` and
  `SesAccountQuota` DTOs: per verified identity the breaker's rolling
  bounce/complaint rates plus a traffic light, the project's suppression
  pressure, and a BYO-SES tenant's own account quota. Read-only by design —
  resuming a paused identity stays a human action in the dashboard. A sandbox
  token is refused with the code `not_available_in_sandbox`.
- **`NotificationsResource::analytics()`** — GET `/notifications/analytics` with
  the `NotificationAnalytics`, `NotificationChannelStats` and
  `PushDeviceRegistry` DTOs: the rolling 30-day push / in-app aggregation. Works
  inside a sandbox, so it is how you read a test run back.
- **`ImportsResource`** (`create`, `list`, `cursor`, `get`) with the `Import`
  DTO — bulk contact imports, the only surface that creates contacts in bulk
  WITH their consent state. Asynchronous: `create()` answers `202` with a
  pending run, poll `get()` until `Import::isFinished()`. An import never
  resurrects an opt-out, and `status` accepts words, never booleans.
- New client accessors `broadcasts()`, `notificationTemplates()`, `imports()`
  and `delivery()`, mirrored on the `Recado` facade.

- **A/B campaign authoring.** `CampaignsResource::create()` and `update()` accept
  the `ab_test` configuration (`enabled`, `test_fraction`, `winner_metric`,
  `test_duration_minutes`) and a `variants` array of 2..4 alternatives
  (`subject`, `preheader`, `from_name`, `from_email`, `content` — each optional,
  each inheriting the campaign field when null). Variants are replaced as a
  whole set and their A..D labels are assigned server-side in array order.
- `Campaign::$abTest`, a new `CampaignAbTest` DTO carrying the authored
  configuration, the `state`, a `locked` flag and the variants. It is present on
  every campaign read and write, unlike the `include=variants` engagement
  breakdown.
- `CampaignVariant` gained the authored fields `preheader`, `fromName`,
  `fromEmail` and `content` alongside its existing engagement numbers; both
  halves are optional, so the DTO now describes the variant in either shape.
- New error codes surfaced by `ValidationException::getErrorCode()`:
  `ab_test_locked` (the test is already running) and `ab_test_requires_plan`.

## [2.5.0] - 2026-09-10

### Added

- `CampaignsResource` gained the whole write surface: `create()`, `update()`,
  `delete()`, `duplicate()`, `send()`, `schedule()`, `unschedule()`,
  `cancel()`, `testSend()`, `preview()`, `readiness()`, `recipientCount()` and
  the batch `stats()`. `list()` now passes the `status` / `search` /
  `scheduled_from` / `scheduled_to` filters, `sort` and `include=stats`, and
  `get()` takes a query array for `include=top_links,variants`.
- **Send safety posture (explicit, per call).** `send(int|string $id, bool
  $confirm = false)` only sends with `confirm: true`; otherwise it throws the
  new `Recado\Sdk\Exception\CampaignSendNotConfirmedException` **before any
  HTTP request is made**, so an accidental `send()` never reaches the audience.
  This replaces the previous "the resource simply has no write methods"
  posture; the file docblock and the README document the new one.
- `SegmentsResource` (`list`, `cursor`, `get`, `create`, `update`, `delete`)
  with a `Segment` DTO. The condition tree stays a plain array — no query DSL.
- `WebhooksResource` (`list`, `create`, `update`, `delete`) with a
  `WebhookEndpoint` DTO. `create()` is the only place the signing `secret` is
  ever returned. Events may be passed as plain strings or as cases of the new
  `Recado\Sdk\Webhooks\WebhookEvent` enum, which carries the full
  subscribable catalog including the campaign lifecycle events
  `campaign.scheduled`, `campaign.started`, `campaign.sent`, `campaign.failed`
  and `campaign.cancelled`.
- `EventsResource` (`list`, `cursor`, `forContact`, `forContactCursor`) with an
  `EventOccurrence` DTO — the read side of `send()->track()`.
- All three resources are reachable from `RecadoClient` (`segments()`,
  `webhooks()`, `events()`) and from the `Recado` facade, and paginate through
  the usual `Paginated` + `cursor()` conventions.
- New DTOs `CampaignPreview`, `CampaignReadiness` + `CampaignReadinessCheck`
  (with a `failures()` helper), `CampaignTopLink` and `CampaignVariant`. The
  `Campaign` DTO gained the nullable `topLinks` / `variants` properties — null
  means "not requested", an empty array means "requested and empty".

- `contacts()->batchUpdate(array $contacts, ?string $idempotencyKey = null)`
  wraps the new `PATCH /contacts/batch` endpoint: a bulk attribute upsert of up
  to 500 contacts in one request (its own 30/min limiter, so a full audience
  refresh no longer spends the shared management budget). Each item carries
  `email` plus any of `attributes`, `first_name`, `last_name`, `locale`,
  `tags_add`, `tags_remove`. Attributes are MERGED per contact — keys absent
  from the payload survive — and the endpoint is deliberately update-only and
  consent-safe: an unknown email comes back as `skipped_not_found` (never
  created), and `status`, `subscribed_at`, `unsubscribed_at` and list
  memberships are never written. The method returns the `data` block
  (`results`, `updated`, `skipped`, `invalid`); a malformed item is reported as
  an `invalid_attributes` row with its own `errors` map instead of aborting the
  batch. The optional idempotency key is sent as `Idempotency-Key`, with the
  same 24h replay / `409 idempotency_conflict` semantics as `send()->batch()`.

### Notes

- The campaign endpoints beyond create/update/send/schedule/unschedule ship
  with the API issues that introduce them; calling one against an older server
  surfaces the usual `404`/`405` through the exception hierarchy.

## [2.4.0] - 2026-09-09

### Added

- `send()->track()` accepts an optional fourth `$contact` array carrying the
  contact fields the endpoint applies to the contact it upserts —
  `first_name`, `last_name`, `name` (split on the first whitespace; the
  explicit fields win) and the already-supported `locale`. All of them follow
  the same policy: set on create, updated when provided, never cleared when
  omitted. The positional `$event`/`$email` and the `data` block always win
  over a same-named key in the array, and passing only the first three
  arguments is byte-identical to before.
- The same `$contact` array also carries `lists` (ids of the project's lists,
  max 50) and `tags` (names, max 25, created on first use), which the endpoint
  attaches to the upserted contact **before** the event is recorded — so an
  event-triggered automation already observes them (e.g. a `has_tag` condition
  on a tag sent with this very call). Both are idempotent and never change the
  contact's subscription status; an unknown list id is rejected with a `422`
  `ValidationException` carrying the code `list_not_found`. Documented only —
  pass-through, no code change.
- `send()->email()` and `send()->batch()` items accept the same `first_name` /
  `last_name` / `name` fields (documented pass-through — the payload was
  already forwarded as-is).
- The Laravel mail transport forwards the recipient's **display name** as the
  `/send` `name` field (`->to(new Address('ada@example.com', 'Ada Lovelace'))`),
  so the platform can fill the contact's first/last name. The name is resolved
  per recipient on single sends, batch items and the attachment fan-out alike;
  a recipient without a display name leaves the payload byte-identical, and the
  name joins the content idempotency key only when present.

### Notes

- The API accepts the `first_name`/`last_name`/`name` fields on `/v1/send`,
  `/v1/send/batch` and `/v1/track` since 2026-09-08, and the `/v1/track`
  `lists`/`tags` fields since 2026-09-09. The `track()` `locale` key has worked
  for longer.

## [2.3.0] - 2026-09-08

> ### ⚠️ Behavior change — read before upgrading
>
> The Laravel mail transport (`MAIL_MAILER=recado`) now **forwards the
> message's own From address** to the API instead of dropping it. Laravel
> stamps the app's global `mail.from` on every message, so **if that address's
> domain is not a verified sending domain of your Recado project, every send
> starts failing with `422 sending_domain_not_verified`** (raised as a
> `TransportException` with an actionable message).
>
> Two ways forward:
>
> 1. **Recommended** — add and verify each sender's domain under *Settings →
>    Sending domains*. You then get what this change is for: several senders on
>    one project, with `->from(...)` on a Mailable actually honoured.
> 2. **Opt out** — set `RECADO_MAIL_FORWARD_FROM=false` (config
>    `recado-sdk.mail.forward_from`) to keep the previous behavior: From and
>    Reply-To are dropped and the project's `default_from_email` always wins.

### Added

- **Notification templates**: `notifications()->send()` and
  `notifications()->batch()` items accept a `template` slug (+ optional
  `variables`) as an alternative to inline `title`/`body` — a plain payload
  pass-through, no signature changes. The API resolves the template's
  locale variants per recipient and snapshots the content at queue time;
  per-send `action_url`/`icon` override the template defaults. An unknown
  slug throws a `ValidationException` with code `template_not_found` on
  `send()`, and surfaces as a per-item/per-channel
  `failed_precondition`/`template_not_found` outcome on `batch()`.

### Changed

- **The Laravel mail transport now forwards the message's own sender.** A
  Mailable calling `->from(...)` / `->replyTo(...)` is mapped onto the `/send`
  `from`, `from_name` and `reply_to` fields (single sends and batch items
  alike), so one project can send from several verified addresses instead of
  always using its `default_from_email`. **Behavior change**: Laravel stamps
  the app's global `mail.from` on every message, so sends that previously went
  out as the project sender now go out as that address — its domain must be a
  verified sending domain of the project or the API answers `422`
  `sending_domain_not_verified` (re-thrown as a `TransportException`). Set
  `recado-sdk.mail.forward_from` to `false` (env
  `RECADO_MAIL_FORWARD_FROM=false`) to keep the previous behavior. A message
  with no From/Reply-To produces exactly the payload it did before.

- **Actionable `sending_domain_not_verified` errors.** A rejected sender now
  raises a `TransportException` naming the refused address, its domain and the
  two ways out (verify the domain, or disable `forward_from`) instead of a
  generic "platform rejected the send" line — on the single-send, whole-batch
  and per-batch-item paths. Other `422` codes keep their existing message.

- The transport's content idempotency key folds in `from`/`from_name`/
  `reply_to` when the message carries them, so the same content sent from two
  different addresses no longer dedupes to one key. A send without a From keeps
  the key it had before.

- The package now ships its own `pint.json` with `laravel/pint` as a dev
  dependency and `composer lint` / `composer lint:check` scripts; Pint and the
  PHPUnit suite run in CI on PHP 8.4 and 8.5 for every change. Source-only —
  no runtime behavior changed (the accompanying formatting pass is style-only).

## [2.2.0] - 2026-08-15

### Added

- **`NotificationsResource::batch()`** for the new
  `POST /notifications/batch` endpoint: 1–100 notification payloads per
  request (10 requests/min per token), each with the same fields as
  `send()` and defaulting to `['in_app']` when no `channels` are given.
  Returns a `NotificationBatchResult` (`queued`/`failed` counts of CHANNEL
  dispatches plus `NotificationBatchItem`s carrying `index`, `to` and the
  per-channel results). Runtime failures stay per item and per channel, so
  the endpoint always answers `202`; a malformed item rejects the whole
  request with a `ValidationException` keyed by index. Supports an optional
  `$idempotencyKey` (24h replay, its own key namespace, `409`
  `idempotency_conflict` while in flight).

- **Send options on `/send` and `/send/batch`** (the payload is passed
  through as-is, so no signature changes): `cc`/`bcc` (max 10 each; copies
  never create contacts, suppressed copy addresses are silently dropped),
  `reply_to`, `from`/`from_name` per-send sender override (`from` must be on
  a verified sending domain when the project enforces it — `422` code
  `sending_domain_not_verified`; on batch it fails per item), custom
  `headers` (max 10 `X-*` names; `X-SES-*`/`X-Recado-*` reserved) and
  `metadata` (up to 10 scalar values, 4 KB serialized). Documented on
  `SendResource::email()`/`batch()`.
- **`Message` DTO** now exposes `cc`, `bcc`, `replyTo` and `metadata`
  (new optional constructor parameters; `fromArray()` reads the
  corresponding API fields).
- **Messages list filter**: `MessagesResource::list()`/`cursor()` accept
  `metadata_key` + `metadata_value` (a single exact-match pair, both
  required together).

## [2.1.0] - 2026-07-07

### Changed

- **Default base URL is now the canonical API host `https://api.recado.dev/v1`**
  (was `https://recado.dev/api/v1`). The platform serves the public API
  canonically at `api.recado.dev/v1`; the legacy apex path
  `https://recado.dev/api/v1` **remains supported** — a consumer pinning it via
  `RECADO_BASE_URL` keeps working and is deliberately NOT rejected by the
  dead-host guard. No other changes.

## [2.0.0] - 2026-07-07

First release under the **Recado** brand. **NO functional changes vs 1.4.0** —
the same code and tests, renamed. Everything brand-carrying is breaking:

### Changed (BREAKING)

- **Package renamed** from `mosaiqo/mailer-php` to **`recado/recado-php`**
  (`mosaiqo/mailer-php` is abandoned on Packagist with this package as the
  recommended replacement; v1.4.0 is its final release).
- **Namespace**: `Mailer\Sdk\*` → **`Recado\Sdk\*`** (all classes).
- **Classes renamed** (same behavior, new names):
  - `MailerClient` → `RecadoClient`
  - `MailerException` → `RecadoException`
  - `MailerConfigurationException` → `RecadoConfigurationException`
  - `MailerServiceProvider` → `RecadoServiceProvider`
  - facade `Mailer` → `Recado` (alias `Recado`)
  - `MailerTransport` → `RecadoTransport`
  - `MailerChannel` → `RecadoChannel`
  - `MailerMessage` → `RecadoMessage`
  - `MailerHeaders` → `RecadoHeaders`
  - Brand-neutral exception subclasses (`ValidationException`,
    `NotFoundException`, `RateLimitException`, `AuthenticationException`,
    `UnsupportedFeatureException`, `AttachmentsTooLargeException`) keep their
    names.
- **Notification contract**: notifications define `toRecado()` (was
  `toMailer()`); recipient routing reads `routeNotificationFor('recado')`
  (was `'mailer'`), with the `'mail'` route and `$email` fallbacks unchanged.
- **Laravel transport/channel string**: `mailer` → **`recado`**
  (`MAIL_MAILER=recado`; `config/mail.php` entry
  `'recado' => ['transport' => 'recado']`).
- **Config**: `config/mailer-sdk.php` → `config/recado-sdk.php`, key
  `mailer-sdk` → `recado-sdk`, publish tag `mailer-sdk-config` →
  `recado-sdk-config`.
- **Env vars**: `MAILER_*` → **`RECADO_*`** (`RECADO_BASE_URL`,
  `RECADO_API_TOKEN`, `RECADO_TIMEOUT`, `RECADO_RETRIES`,
  `RECADO_RETRY_BASE_DELAY`, `RECADO_RETRY_MAX_DELAY`,
  `RECADO_MAIL_ATTACHMENTS`, `RECADO_MAIL_IDEMPOTENCY`).
- **SDK message headers**: `X-Mailer-Template` / `X-Mailer-Variables` /
  `X-Mailer-Idempotency-Key` → **`X-Recado-*`** (SDK-internal — consumed and
  stripped by the transport, they never cross the wire). The brand-neutral
  `Idempotency-Key` HTTP header sent to the API is unchanged.
- **Default base URL**: `https://recado.dev/api/v1` (was
  `https://mailer.mosaiqo.com/api/v1`).
- **Dead-host guard extended**: a base URL pointing at the decommissioned
  `mailer.mosaiqo.com` host now throws `RecadoConfigurationException` at
  construction (that host is being killed with the domain migration — a stale
  config fails loudly instead of POSTing into the void). The old
  `api.mailer.test` placeholder is still rejected too.

## [1.4.0] - 2026-07-05

### Added

- **Attachment support on the Laravel mail transport**, with a new
  `mailer-sdk.mail.attachments` mode **`'send'` as the default**: the message's
  attachments are mapped onto the platform `/send` `attachments` field
  (`filename` from the attachment — unnamed parts get `attachment` plus an
  extension inferred from the media type —, `content_type` from the media
  type/subtype, `content` base64-encoded). Works for inline and template sends.
- **Per-recipient fan-out for multi-recipient sends with attachments.**
  `/send/batch` rejects attachments (single-send only), so the transport sends
  each recipient its own `/send` call instead. Every fan-out send gets a
  distinct per-recipient idempotency key; an explicit
  `X-Mailer-Idempotency-Key` override is derived per recipient
  (`{key}:{sha1(recipient) prefix}`) so the platform never dedupes recipients
  against each other.
- **Local total-size guard**: when the decoded attachments of one send exceed
  the platform's 10 MB per-send limit, the SDK throws the new
  `Mailer\Sdk\Exception\AttachmentsTooLargeException` (error code
  `attachments_too_large`, matching the server's `422`) *before* uploading.
  Per-file limits and the executable-extension blocklist remain server-side.
- Attachments now participate in the `content` idempotency key, so a requeued
  job with the same attachments still dedupes while a changed attachment
  produces a new key.
- Docs: attachments section in the README (modes table, limits, filename
  blocklist, batch behavior, the `attachments_too_large` error) and
  `attachments` documented on `send()->email()` / prohibited on
  `send()->batch()`.

### Changed

- **The default `mail.attachments` mode is now `'send'`** (was `'fail'`).
  Consumers who relied on the fail-loud behavior — an
  `UnsupportedFeatureException` for any message carrying an attachment — must
  now set `MAILER_MAIL_ATTACHMENTS=fail` (or
  `mailer-sdk.mail.attachments = 'fail'`) explicitly. `'ignore'` is unchanged.

## [1.3.1] - 2026-07-05

### Fixed

- **`content` idempotency no longer silently drops sends to different
  recipients.** On the Laravel mail transport path the idempotency key was
  computed from the content *before* the recipient was merged, so identical
  content sent to two different recipients produced the same `txn_…` key and the
  platform deduped the later sends (no exception, no log — silent data loss). The
  key is now computed per recipient (single send) and from the sorted recipient
  list (batch), so a requeued job still dedupes while distinct recipients/lists
  get distinct keys. An explicit `X-Mailer-Idempotency-Key` header still
  overrides everything.

## [1.3.0] - 2026-07-04

### Changed

- `MAILER_BASE_URL` is now **optional** and defaults to the hosted API
  (`https://mailer.mosaiqo.com/api/v1`), so hosted consumers only need to set
  `MAILER_API_TOKEN`. Self-hosted consumers still set `MAILER_BASE_URL` to their
  own endpoint. This relaxes part of the v1.1.0 fail-loud change: the token
  stays required, and an explicitly empty or `api.mailer.test` placeholder base
  URL still throws `MailerConfigurationException` at construction.

## [1.2.0] - 2026-07-04

### Added

- **Notifications resource** (`notifications()->send()`) — multichannel
  (in-app + push) notification sends. The SDK always requests the per-channel
  envelope (`in_app` is injected when no `channels` are given) and hydrates a
  `NotificationResult` of `NotificationChannelResult`s. Per-channel failures are
  DATA, not exceptions: the all-channels-failed `422` (which carries the same
  `{data:{messages:[…]}}` envelope) is returned as a `NotificationResult`
  (`anyQueued()` false), while a real validation `422` still throws
  `ValidationException`.
- **Push tokens resource** (`push()->register()` / `push()->remove()`) — register
  and remove device tokens for push delivery, returning a `PushTokenResult`.
- **Sandbox resource** (`sandbox()->simulate()`) — drive the real delivery
  pipeline from a sandbox project's API token by simulating provider/engagement
  events (`EVENT_DELIVERED`, `EVENT_HARD_BOUNCE`, `EVENT_SOFT_BOUNCE`,
  `EVENT_COMPLAINT`, `EVENT_OPEN`, `EVENT_CLICK`, `EVENT_READ`) on a message,
  returning a `SimulatedEvent`. A production token gets a bare `404`.
- `notifications()`, `push()` and `sandbox()` accessors on the `Mailer` facade.

### Fixed

- **`RetryMiddleware` caps a 429 `Retry-After`** at the configured
  `retry_max_delay`. Previously the server-provided value was honored with no
  upper bound, so a hostile or misconfigured `Retry-After` could block a
  synchronous worker indefinitely. It is still honored up to the cap.
- **`push()->register()` documents only `ios`/`android` platforms.** The
  docblock and README previously listed `web`, but the `/push/tokens` endpoint
  accepts native FCM device tokens only (`in:ios,android`) — web push uses the
  separate VAPID subscription flow. A doc-following consumer passing `web`
  received a runtime `422`. No API change; the SDK docs are now correct.
- **README error-handling accuracy.** The error-handling docs listed the sandbox
  click failure as `link_index`; the API actually emits `link_index_out_of_range`.
  The idempotency section claimed an `idempotency_conflict` raises a
  `ValidationException`; the API returns it as a `409`, which the SDK surfaces as
  the base `MailerException` (a `ValidationException` `422` is instead the
  `invalid_idempotency_key` case). No code change; the docs now match what the
  API and the `HttpClient` status mapping actually do.
- `composer.json` now requires PHP `>= 8.3`. The SDK has used typed class
  constants (a PHP 8.3 feature) since v1.1.0, so installs on PHP 8.2 already
  fataled on class load — the constraint now matches what actually runs
  instead of letting Composer install a package that cannot boot.

## [1.1.2] - 2026-06-17

### Changed

- Docs: the package is now published on **Packagist**, so installation is just
  `composer require mosaiqo/mailer-php:^1.1` — the README and `AGENTS.md` drop
  the Composer VCS `repositories` entry and the private-repo SSH/deploy-key
  steps (no longer needed). Removed the private-repo deploy-access human gate
  from the agent playbook.

## [1.1.1] - 2026-06-17

### Added

- `AGENTS.md` — a terse, imperative integration playbook for AI agents wiring
  the SDK into a consuming Laravel app, with the human-gate steps (API key,
  template slug) called out. README links to it.

## [1.1.0] - 2026-06-17

### Added

- Integration guide in the README: a copy-paste recipe to wire the SDK into a
  real Laravel app (private Composer VCS repo, `composer require`, env vars,
  `config/mail.php` mailer entry, send examples, where to get the project API
  key, and the deploy-time SSH read-access note for the private repo).
- `MailerConfigurationException` (extends `MailerException`) — raised at client
  construction on missing configuration.

### Changed

- **Fail-loud connection config (behavior change).** `MAILER_BASE_URL` no longer
  has a working-looking default (`https://api.mailer.test/api/v1`). Constructing
  the client with an unset/empty/placeholder base URL — or an empty
  `MAILER_API_TOKEN` — now throws a `MailerConfigurationException` with a clear
  message, instead of silently sending to a dead host. **Action required:** set
  `MAILER_BASE_URL` (and `MAILER_API_TOKEN`) explicitly; a previously published
  `config/mailer-sdk.php` still carrying the old default is rejected too.

## [1.0.0]

### Added

- Laravel mail transport driver (`MAIL_MAILER=mailer`) — route the `Mail`
  facade, Mailables and queued mailers through the platform `/send` API, with
  documented behavior for attachments, suppressed recipients, quota / sending-
  domain rejections, From/Reply-To, multi-recipient batches, idempotency modes
  and the `X-Mailer-Template` header.
- Laravel notification channel (`mailer`) — deliver notifications via `via()` +
  `toMailer()` returning a `MailerMessage` (inline or stored template).
- `Mailer` facade proxying the container-bound `MailerClient` singleton.
- Automatic retries with exponential backoff (idempotency-safe) on the built-in
  HTTP client.
- Lazy pagination — `cursor()` generators that walk every page on demand
  (wrappable in a Laravel `LazyCollection`).
- Read-only campaigns resource (`campaigns()->list()` / `get()` with stats).

[Unreleased]: https://github.com/recado-dev/recado-php/compare/v2.5.0...main
[2.5.0]: https://github.com/recado-dev/recado-php/compare/v2.4.0...v2.5.0
[2.4.0]: https://github.com/recado-dev/recado-php/compare/v2.3.0...v2.4.0
[2.3.0]: https://github.com/recado-dev/recado-php/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/recado-dev/recado-php/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/recado-dev/recado-php/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/recado-dev/recado-php/compare/v1.4.0...v2.0.0
[1.4.0]: https://github.com/recado-dev/recado-php/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/recado-dev/recado-php/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/recado-dev/recado-php/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/recado-dev/recado-php/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/recado-dev/recado-php/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/recado-dev/recado-php/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/recado-dev/recado-php/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/recado-dev/recado-php/releases/tag/v1.0.0
