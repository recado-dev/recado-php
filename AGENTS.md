# AGENTS.md — mailer-php integration playbook

**Goal:** Wire a Laravel app to send email through mailer-app via this SDK.

For an AI agent integrating `mosaiqo/mailer-php` into a **consuming** Laravel app.
Terse and imperative — do exactly this. Full human reference: [README.md](README.md).

## Steps

1. Add the VCS repo to the consuming app's `composer.json` (private repo, **not**
   on Packagist):
   ```json
   "repositories": [
       { "type": "vcs", "url": "git@github.com:mosaiqo/mailer-php.git" }
   ]
   ```
2. Require it:
   ```bash
   composer require mosaiqo/mailer-php:^1.1
   ```
   (Before the `v1.1.0` tag is published, require `mosaiqo/mailer-php:dev-main`.)
3. Set env. Both connection vars are **REQUIRED** — there is NO default; a
   missing/empty/placeholder value throws `MailerConfigurationException` at boot
   instead of silently sending to a dead host:
   ```dotenv
   MAIL_MAILER=mailer
   MAILER_BASE_URL=https://<your-mailer-app-host>/api/v1
   MAILER_API_TOKEN=<project API key>
   ```
4. Add the mailer to `config/mail.php` under `mailers`:
   ```php
   'mailer' => ['transport' => 'mailer'],
   ```
5. Send — pick one:
   - Mailable / plain message through the transport:
     ```php
     Mail::to('jane@example.com')->send(new WelcomeMail($user));
     ```
   - API client with a stored template by slug:
     ```php
     use Mailer\Sdk\Laravel\Facades\Mailer;

     Mailer::send()->email([
         'to' => 'jane@example.com',
         'template' => 'welcome',          // slug must already exist (see gates)
         'variables' => ['first_name' => 'Jane'],
     ]);
     ```
6. **VERIFY** (do not declare done without this): send ONE test email to a real
   inbox you control and confirm the call returns `202` / the message arrives.
   Auth is the project API key (Sanctum bearer token); a missing/invalid token
   returns `401`.

## ⚠️ Human gates — STOP and ask the human; do NOT do these yourself

- **API key** — created in the mailer-app UI: **Settings → API keys**, per
  project. REQUEST it from the human. Never invent, guess, or hardcode a token.
- **Private-repo deploy access** — `mosaiqo/mailer-php` is a **private** repo.
  The deploy server (Forge, CI, etc.) needs SSH read access to it (deploy key /
  machine user), or `composer install` on deploy FAILS. Flag this to the human
  before deploying.
- **Template slug** — a `template` send needs that slug to ALREADY exist on the
  mailer-app server. Confirm with the human (or have them create it there)
  before using it; an unknown slug is rejected (`422 template_not_found`).

## Discoverability

This file ships inside `vendor/mosaiqo/mailer-php/AGENTS.md`, and agents don't
scan `vendor/`. When you integrate, add a one-line pointer to the **consuming**
repo's own `CLAUDE.md` / `AGENTS.md`:

> To send email use mailer-php — follow `vendor/mosaiqo/mailer-php/AGENTS.md`.

## More

Full reference — config keys, fail-loud config, retries, error handling, batch
sends, notification channel, facade: [README.md](README.md).
