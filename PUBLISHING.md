# Publishing & distribution

This package lives inside the Recado monorepo at `sdk/php/`, but it is consumed
as a **standalone Composer package**, published on
[Packagist](https://packagist.org/packages/recado/recado-php) as
`recado/recado-php`. This document describes how the standalone mirror repo is
produced, how releases are tagged, and how the old `mosaiqo/mailer-php` package
is abandoned.

> The standalone mirror repository is `git@github.com:recado-dev/recado-php.git`
> (web: <https://github.com/recado-dev/recado-php>), used throughout the
> commands below. The mirror is **read-only** (force-pushed by CI); PRs opened
> on it are auto-closed — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Why a subtree split?

Composer reads the package metadata from `composer.json` **at the repository
root**. In the monorepo that file lives at `sdk/php/composer.json`, so the
monorepo itself is not directly installable. A `git subtree split` extracts the
`sdk/php/` directory (with its own history) into a branch whose root **is**
`sdk/php/`, putting `composer.json` exactly where Composer expects it. Pushing
that branch to the dedicated mirror repo gives a clean, installable, taggable
package — the `.gitattributes` `export-ignore` rules then keep the dist tarball
lean (no `tests/`, no `phpunit.xml.dist`, ...).

## Automated distribution (the normal path)

The split is automated by the monorepo GitHub Actions workflow
`.github/workflows/split-sdk.yml`:

- **Sync on push.** Every push to the monorepo `main` branch that touches
  `sdk/php/**` re-runs `git subtree split --prefix=sdk/php`, strips the
  mirror-managed `.github/` directory from the split (the deploy key lacks the
  `workflow` scope, so a split carrying workflow files would be rejected
  wholesale) and force-pushes the result as the mirror's `main`.
- **Release on dispatch.** To cut a SemVer release, run the workflow named
  **"Split SDK to recado-php"** with `workflow_dispatch` and provide the `tag`
  input (e.g. `v2.0.0`); the workflow tags the split branch on the mirror and
  pushes the tag.
- The workflow requires the `RECADO_PHP_DEPLOY_KEY` repository secret — an SSH
  deploy key with write access to `recado-dev/recado-php`.

## Packagist

The package **is published on Packagist** (v1.4.0 was the last release under
the old name), so consumers just `composer require recado/recado-php:^2.0` —
no VCS repository entry, no SSH access.

- **Auto-update hook.** Packagist should update automatically on every mirror
  push: install the **Packagist GitHub App** on the `recado-dev` org (or the
  legacy webhook: repo → Settings → Webhooks → the packagist.org service hook
  with the API token from your Packagist profile). Without the hook, releases
  only appear after a manual "Update" click on the package page.
- **New tags = new releases.** Composer resolves versions from the Git tags on
  the mirror repo — tagging `v2.x.y` there (via the workflow dispatch above) is
  what publishes a release.
- **First-time submit.** The renamed package must be submitted once at
  <https://packagist.org/packages/submit> with the mirror URL
  `https://github.com/recado-dev/recado-php` (needs the mirror repo to exist
  and carry `composer.json` with the `recado/recado-php` name at its root).

## Versioning & tags (SemVer)

Follow [SemVer](https://semver.org/):

- **MAJOR** (`v2.0.0`) — a breaking change to the public SDK API (renamed /
  removed public methods, changed signatures or DTO shapes, dropped PHP /
  Laravel versions).
- **MINOR** (`v2.1.0`) — backward-compatible features (new resources, new
  optional arguments).
- **PATCH** (`v2.0.1`) — backward-compatible bug fixes.

### Cutting a new release after monorepo changes

1. Land the SDK changes in the monorepo under `sdk/php/`.
2. Update `CHANGELOG.md` (move items from `[Unreleased]` into a versioned
   section).
3. Let the push-sync update the mirror's `main` (automatic on merge).
4. Dispatch the split workflow with the `tag` input (e.g. `v2.0.1`). Packagist
   picks the tag up via the auto-update hook.

## Manual fallback (SSH)

When CI is unavailable, the split can be run by hand from the monorepo root —
this is exactly what the workflow automates:

```bash
# Create a branch whose root is sdk/php/ (composer.json lands at the top).
git subtree split --prefix=sdk/php -b sdk-php-split

# Strip the mirror-managed .github directory (the deploy key cannot push
# workflow files) before pushing.
git checkout sdk-php-split
git rm -r --ignore-unmatch .github
git commit -m "chore: drop mirror-managed .github from split" || true

# Force-push that branch as `main` on the mirror repo.
git push --force git@github.com:recado-dev/recado-php.git sdk-php-split:main

# Tag a release on the split branch and push the tag.
git tag -a v2.0.0 sdk-php-split -m v2.0.0
git push git@github.com:recado-dev/recado-php.git v2.0.0
```

`--prefix=sdk/php` is the load-bearing flag: it is what makes
`sdk/php/composer.json` become the repository-root `composer.json` on the split
branch. Requires an SSH key with write access to the mirror (the same deploy
key CI uses, or a maintainer's own key).

### Full-history rewrite alternative

`git subtree split` preserves the relevant commits but keeps monorepo commit
hashes. For a repository whose history contains **only** `sdk/php/` paths
(smaller, rewritten hashes), use
[`git-filter-repo`](https://github.com/newren/git-filter-repo):

```bash
git clone <monorepo> recado-php && cd recado-php
git filter-repo --subdirectory-filter sdk/php
git remote add origin git@github.com:recado-dev/recado-php.git
git push -u origin main
```

This rewrites history, so only do it on a fresh clone dedicated to the split.

## Abandoning `mosaiqo/mailer-php` (operator steps, one-time)

v2.0.0 renamed the package from `mosaiqo/mailer-php`; the old package stays on
Packagist (v1.x installs must keep working) but must clearly point consumers at
the replacement:

1. **Packagist "Abandon".** On the
   [`mosaiqo/mailer-php` package page](https://packagist.org/packages/mosaiqo/mailer-php)
   (as a maintainer) click **Abandon** and enter `recado/recado-php` as the
   recommended replacement. Composer then prints the "Package is abandoned,
   use recado/recado-php instead" warning on every install.
2. **Final commit on the old mirror.** Add `"abandoned": "recado/recado-php"`
   to the old mirror's root `composer.json` (one last commit on
   `mosaiqo/mailer-php`'s `main`), so even VCS-repo consumers see the notice.
3. **Archive the old repo.** GitHub → `mosaiqo/mailer-php` → Settings →
   Archive. Existing tags stay installable; nothing new lands there.

No new tags are ever pushed to the old package — v1.4.0 is its final release.
