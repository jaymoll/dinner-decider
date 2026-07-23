# Dinner Decider operations runbook

Last reviewed: 22 July 2026

This runbook covers the MVP Laravel 13 / PHP 8.5 / MySQL 8.4 deployment. Adapt commands to the selected host and complete every owner/target field before production approval.

## Production prerequisites

- PHP 8.3 or newer with BCMath, PDO MySQL, and Fileinfo; PHP 8.5 is the preferred runtime.
- MySQL 8.4, Composer 2, Node 22.12 or 24 for builds, and a TLS-terminating web server or deployment proxy.
- A persistent volume for `storage/app/public/recipe-images` and coordinated database/file backups.
- The web server must route requests through `public/index.php`, serve `public/storage`, and explicitly refuse script execution beneath `public/storage`.
- `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, HTTPS URLs, secure cookies, and trusted proxy settings appropriate to the host.

Required secrets/configuration include `APP_KEY`, `APP_URL`, database credentials, mail credentials, session/cache configuration, `QUEUE_CONNECTION=sync`, and Fortify passkey `relying_party.id`, `allowed_origins`, and `user_handle_secret`. Store secrets in the host secret store, not source control or build logs.

## Deploy

1. Put the application into maintenance mode when the change is not backward compatible: `php artisan down --retry=60`.
2. Install locked PHP dependencies: `composer install --no-dev --classmap-authoritative --no-interaction`.
3. Install and build locked frontend dependencies: `npm ci && npm run build`.
4. Link public storage once per release target: `php artisan storage:link`.
5. Run migrations: `php artisan migrate --force --no-interaction`.
6. Cache framework metadata: `php artisan optimize`.
7. Reload PHP-FPM or the application service so long-running processes use the new code.
8. Restore service: `php artisan up`.
9. Verify `/up`, login, pantry, recommendations, dinner plan, groceries, an image and a no-image recipe, and recent application/browser logs.

Use an atomic release-directory switch where the host supports it. Do not run `db:seed` in production: `DatabaseSeeder` deliberately refuses to create demo credentials there.

## Rollback

Switch back to the previous application release and rebuild its optimized caches. Roll database migrations back only when the migration is explicitly documented as safely reversible and no newer data would be lost. Otherwise restore the coordinated database and recipe-image backup into an isolated environment first, validate it, and schedule the production restore. Record the release identifier, schema batch, backup identifiers, decision owner, and incident timeline.

## Coordinated backup

Database rows and `storage/app/public/recipe-images` are one recovery set. Pause writes or use a storage/database snapshot mechanism that provides a common recovery point.

Example Docker/Sail database backup:

```powershell
docker compose exec -T mysql sh -lc 'mysqldump --single-transaction --routines --triggers -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > dinner-decider.sql
```

Archive `storage/app/public/recipe-images` without following links and calculate checksums for both artifacts. Encrypt backups before transfer and store the database dump, image archive, checksums, application release, and timestamp under one recovery-set identifier.

The selected host must define:

| Field | Production value |
| --- | --- |
| RPO | To be approved |
| RTO | To be approved |
| Retention and deletion schedule | To be approved |
| Encryption method and key owner | To be approved |
| Backup storage and access policy | To be approved |
| Restore owner and deputy | To be approved |
| Backup/restore alert destination | To be approved |

## Restore drill

Restore only into an isolated environment first. Provision the matching application release and MySQL version, import the SQL dump, restore recipe images to `storage/app/public/recipe-images`, create the storage link, run `php artisan optimize`, and verify migrations. Compare row counts and checksums, confirm owned relationships and dinner snapshots, load stored images and placeholders, then run the MVP smoke journey. Record duration, achieved RPO/RTO, failures, and remediation before approving a production restore.

## Runtime processes

`QUEUE_CONNECTION=sync` is authoritative for the MVP. No queue worker is required. Before activating a future worker, define after-commit dispatch, supervised processes, retry/backoff and timeout policy, idempotency, failed-job storage and alerts, then use `php artisan queue:restart` (or the host's `artisan reload` workflow) during deploys.

No scheduler process is required until a real scheduled task exists. When one is introduced, document its owner, overlap protection, timezone, failure alerts, and single-server execution policy.

## Security and edge ownership

The deployment proxy owns TLS redirection and HSTS. CSP also remains proxy-owned but must begin in report-only mode and be validated against Flux, Livewire, Vite assets, passkeys, and any required inline/runtime styles before enforcement. Production cookies must be Secure, HttpOnly where applicable, and use the intended SameSite policy.

Recipe uploads accept only successfully uploaded JPEG, PNG, and WebP content that passes server-side image parsing and configured byte/dimension limits. The application generates the filename and stores only a managed relative path. Keep the web-server no-execution rule. Security re-encoding is deferred until GD is approved as a required platform extension.

Fortify's generated two-factor QR SVG is the only deliberate trusted raw SVG output. It originates from Fortify, not user-uploaded content. User recipe SVGs and GIFs are rejected.

## Health and incident checks

Check service/container health, `/up`, `php artisan migrate:status`, recent Laravel logs, recent browser logs, database capacity/locks, disk capacity for recipe images, and backup freshness. Generic production error pages must remain enabled through `APP_DEBUG=false`; inspect correlated server logs rather than exposing exceptions to users.
