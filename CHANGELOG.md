# Changelog

All notable changes to `recado/recado-php` (formerly `mosaiqo/mailer-php`,
through v1.4.0) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/recado-dev/recado-php/compare/v2.1.0...main
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
