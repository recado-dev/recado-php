# Changelog

All notable changes to `mosaiqo/mailer-php` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/mosaiqo/mailer-php/compare/v1.3.0...main
[1.3.0]: https://github.com/mosaiqo/mailer-php/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/mosaiqo/mailer-php/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/mosaiqo/mailer-php/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/mosaiqo/mailer-php/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/mosaiqo/mailer-php/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/mosaiqo/mailer-php/releases/tag/v1.0.0
