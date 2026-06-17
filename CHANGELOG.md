# Changelog

All notable changes to `mosaiqo/mailer-php` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `AGENTS.md` — a terse, imperative integration playbook for AI agents wiring
  the SDK into a consuming Laravel app, with the human-gate steps (API key,
  private-repo deploy access, template slug) called out. README links to it.

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

[Unreleased]: https://github.com/mosaiqo/mailer-php/compare/v1.1.0...main
[1.1.0]: https://github.com/mosaiqo/mailer-php/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/mosaiqo/mailer-php/releases/tag/v1.0.0
