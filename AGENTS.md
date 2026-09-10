# AGENTS.md — recado-php integration playbook

**Goal:** Wire a Laravel app to send email through Recado via this SDK.

For an AI agent integrating `recado/recado-php` into a **consuming** Laravel app.
Terse and imperative — do exactly this. Full human reference: [README.md](README.md).

## Steps

1. Require the package (published on Packagist — no repo entry or Git/SSH access
   needed):
   ```bash
   composer require recado/recado-php:^2.0
   ```
2. Set env. `RECADO_API_TOKEN` is **REQUIRED**; `RECADO_BASE_URL` is optional
   and defaults to the canonical hosted API (`https://api.recado.dev/v1`; the
   legacy apex path `https://recado.dev/api/v1` remains valid) — set it only
   for self-hosted/other environments. An empty token, a placeholder base URL
   or the decommissioned `mailer.mosaiqo.com` host throws
   `RecadoConfigurationException` at boot instead of silently sending to a
   dead host:
   ```dotenv
   MAIL_MAILER=recado
   RECADO_API_TOKEN=<project API key>
   # RECADO_BASE_URL=https://<your-recado-host>/api/v1   # self-hosted only
   ```
3. Add the mailer to `config/mail.php` under `mailers`:
   ```php
   'recado' => ['transport' => 'recado'],
   ```
4. Send — pick one:
   - Mailable / plain message through the transport:
     ```php
     Mail::to('jane@example.com')->send(new WelcomeMail($user));
     ```
   - API client with a stored template by slug:
     ```php
     use Recado\Sdk\Laravel\Facades\Recado;

     Recado::send()->email([
         'to' => 'jane@example.com',
         'template' => 'welcome',          // slug must already exist (see gates)
         'variables' => ['first_name' => 'Jane'],
     ]);
     ```
5. **VERIFY** (do not declare done without this): send ONE test email to a real
   inbox you control and confirm the call returns `202` / the message arrives.
   Auth is the project API key (Sanctum bearer token); a missing/invalid token
   returns `401`.

## ⚠️ Human gates — STOP and ask the human; do NOT do these yourself

- **API key** — created in the Recado UI: **Settings → API keys**, per
  project. REQUEST it from the human. Never invent, guess, or hardcode a token.
- **Template slug** — a `template` send needs that slug to ALREADY exist on the
  Recado server. Confirm with the human (or have them create it there)
  before using it; an unknown slug is rejected (`422 template_not_found`).
- **Sending a campaign** — `campaigns()->send($id, confirm: true)` fires real
  mail at a real audience and cannot be recalled. ASK the human before calling
  it; without `confirm: true` the SDK throws
  `CampaignSendNotConfirmedException` locally (no request is made), which is
  the guard, not a formality. `readiness()`, `preview()` and `testSend()` are
  the safe ways to check a campaign yourself.

## Discoverability

This file ships inside `vendor/recado/recado-php/AGENTS.md`, and agents don't
scan `vendor/`. When you integrate, add a one-line pointer to the **consuming**
repo's own `CLAUDE.md` / `AGENTS.md`:

> To send email use recado-php — follow `vendor/recado/recado-php/AGENTS.md`.

## Other resources

Beyond email sends, the client exposes:

- `Recado::notifications()->send([...])` — multichannel (in-app + push)
  notifications; per-channel failures are DATA, not exceptions.
- `Recado::push()->register($email, $token, $platform)` / `->remove($email, $token)`
  — manage device tokens for push delivery.
- `Recado::sandbox()->simulate($uuid, $event)` — drive the pipeline from a
  sandbox token in CI (see **Testing with the sandbox** in [README.md](README.md)).
- `Recado::campaigns()` — the newsletter lifecycle (create/update/preview/
  readiness/schedule/cancel/duplicate/delete + the gated `send`).
- `Recado::segments()`, `Recado::webhooks()`, `Recado::events()` — campaign
  targeting, outbound webhook endpoints, and the read side of `track()`.

## More

Full reference — config keys, fail-loud config, retries, error handling, batch
sends, notification channel, facade, sandbox testing: [README.md](README.md).
