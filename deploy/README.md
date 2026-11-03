# Backend deployment

The backend is its own repository (`asc-nest/sk-portal-backend`). Commit
`.github/workflows/backend.yml` and `deploy/` at that repository's root.
Pushes and pull requests to `main` run PHP syntax checks and the existing test
suite against a fresh MySQL 8 database. Sample data is seeded only in CI because
the current tests expect seeded accounts and an invitation.

After checks pass, pushes to `main` deploy over SSH. You can also select `main`
under Actions → Backend checks and deployment → Run workflow. Pull requests
never deploy. Production deployment jobs run one at a time.

## One-time server setup

1. Enable SSH access. The account needs Bash, Git, PHP 8.4 CLI (with the required
   Laravel/database extensions), Composer 2, and write access to the checkout,
   `storage/`, and `bootstrap/cache/`. PHP CLI and the website should use compatible
   PHP versions. Ensure these commands are on the non-interactive SSH PATH.
2. Clone the backend repository on the server and check out `main`. Set its
   `origin` credentials so that the server account can run `git fetch origin main`
   without a password prompt. For a private repository, use a separate read-only
   GitHub deploy key on the server; the Actions SSH key is for logging into the
   server, not fetching from GitHub.
3. Configure the production `.env` on the server using `.env.example`. Keep the
   existing `APP_KEY` for an existing installation. Set `APP_URL` to
   `https://sk-portal.be.asc-nest.org`, `FRONTEND_URL` to
   `https://sk-portal-frontend.vercel.app`, and `DB_COLLATION=utf8mb4_unicode_ci`.
   Create the database and grant the application user migration permissions.
4. Set the Apache document root to the checkout's `public/` directory. Deploy
   `public/.htaccess`, enable `mod_rewrite`, and permit its rewrite rules through
   the host's `AllowOverride` configuration.
5. Add the public half of the Actions SSH key to the server account's
   `~/.ssh/authorized_keys`. Keep the private half in GitHub, not in this repository.
6. Commit the runtime-file cleanup below with the workflow changes. The script
   refuses application edits and divergent history. The first automated pull
   preserves runtime data from older checkouts that still track it.

## Required cleanup of currently tracked runtime files

At the time this workflow was added, Git tracks `bootstrap/cache/packages.php`,
`bootstrap/cache/services.php`, `database/database.sqlite`, compiled views,
`storage/logs/laravel.log`, and two avatar uploads. These files have now been
removed from the development Git index with `git rm --cached`; local copies
remain on disk and `.gitignore` excludes them. Commit those staged removals
with the updated deployment script and workflow.

Let the updated GitHub Action perform the first server pull of that commit.
Do not manually pull the cleanup commit without backing up runtime data first,
because Git can delete files that were previously tracked.

When the server still tracks these paths, the script creates a private archive
under `${TMPDIR:-/tmp}/sk-portal-runtime.*` of `storage/`, `bootstrap/cache/`, and
the SQLite file if present. It checks the archive before resetting only those
backed-up tracked runtime files, fast-forwards to the tested cleanup commit,
and restores the live copies. An exit trap attempts restoration if the update
fails. The backup path is printed and retained for manual recovery.

The script does not permit incoming commits to track runtime data again and
does not reset application source edits. It creates Laravel's runtime directories
after pulling; they must be writable by PHP. Backups in `/tmp` are temporary and
are not a replacement for host backups. If the live application uses SQLite,
stop writes and take a database-consistent backup before the first cleanup run.

For a brand-new installation, follow the first-deployment commands in
`.env.example`, including key generation and role seeding. The automated workflow
does not generate keys, seed accounts, or reset the database.

## GitHub configuration

In the backend repository, create a GitHub environment named `production` under
Settings → Environments. Add these environment secrets (repository Actions
secrets also work):

| Secret | Value |
| --- | --- |
| `GODADDY_HOST` | SSH hostname or IP from your hosting provider |
| `GODADDY_PORT` | SSH port; optional, defaults to `22` |
| `GODADDY_USER` | Hosting account with access to the checkout |
| `GODADDY_SSH_KEY` | Complete private SSH key, without a passphrase, dedicated to deployment |
| `GODADDY_DEPLOY_PATH` | Absolute path to the server checkout containing `.git`, `artisan`, and `composer.json`; not the `public/` directory |

The workflow builds `known_hosts` automatically with `ssh-keyscan`, matching the
other GoDaddy project workflows. No known-hosts secret is required. It trusts
the key returned by the host during each run rather than comparing it with a
previously verified key.
Restrict the `production` environment to the `main` branch if your GitHub plan
supports deployment branch rules. No approval step is added by this workflow.

## Deployment behavior and failures

The script fetches `main` and fast-forwards to the exact commit that passed CI,
rather than blindly pulling a newer, untested commit. It installs production
Composer packages, clears stale configuration, runs pending migrations, and
creates the public storage link when absent. It preserves the server's `.env`
and does not seed demo users or run destructive migration commands.

Configuration caching is deliberately omitted because the invitation controller
currently calls `env('FRONTEND_URL')` outside configuration files.

The final HTTPS check requests `/up`. It verifies that Laravel responds, not
database connectivity or login. If CORS fails while `/index.php/api/...` works,
check Apache rewrite support and deployment of `public/.htaccess`.

This updates the existing checkout in place. It is not an atomic release and
does not promise zero downtime or automatic rollback. A Composer/migration/health
failure fails the workflow but can leave a partial deployment; inspect the logs,
fix the cause, and rerun or deploy a corrective commit. Take a database backup
before releases with schema changes. If OPcache timestamp checks are disabled,
arrange a host-specific PHP reload after deployment. Queue workers are only
needed if production changes `QUEUE_CONNECTION` from `sync`.

## Local verification

Workflow YAML passed parsing and Actionlint; the deploy script passed Bash syntax
checks; Composer configuration passed validation. In an isolated temporary copy,
dependency installation, migrations, and seeding succeeded, and PHPUnit passed
all 6 tests (49 assertions) using SQLite and PHP 8.4. No project `.env` was read or
changed. The workflow's MySQL service and SSH deployment still require a real
GitHub Actions run after server setup and secret configuration.

The runtime cleanup was also exercised in isolated local Git repositories:
modified uploads/cache/SQLite copies and new uploads survived the cleanup;
repeat deployment succeeded; application edits were rejected and preserved;
and an injected merge failure restored backed-up runtime files. This checks
the checkout-update block, not the live SSH host or its filesystem permissions.
