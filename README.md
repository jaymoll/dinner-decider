# Dinner Decider

Dinner Decider is a Laravel 13, Livewire 4, Flux UI 2 application backed by MySQL 8.4. The application and database run through Docker Compose using Laravel Sail.

## Supported runtimes

- Preferred Docker runtime: PHP 8.5 and Node 24
- Supported minimum: PHP 8.3 and Node 22.12
- Database: MySQL 8.4

CI tests both supported PHP/Node pairs against MySQL. Composer requires BCMath because future quantity calculations depend on fixed-precision arithmetic.

## First-time Docker setup

Copy the environment file:

```powershell
Copy-Item .env.example .env
```

On a fresh clone, install the Sail runtime files with the Laravel Sail Composer image:

```powershell
docker run --rm -v "${PWD}:/var/www/html" -w /var/www/html laravelsail/php85-composer:latest composer install --ignore-platform-reqs --no-interaction
```

Build and start the healthy stack, then finish application setup inside the container:

```powershell
docker compose up -d --build --wait --wait-timeout 120
docker compose exec -T laravel.test composer setup
docker compose exec -T laravel.test composer check-platform-reqs
```

The application is available at `http://localhost:8000` by default. MySQL is forwarded to host port `3307`; application containers connect to it as `mysql:3306`.

## Health checks

Both services must report `healthy`:

```powershell
docker compose ps
Invoke-WebRequest -UseBasicParsing http://localhost:8000/up
docker compose exec -T laravel.test php artisan migrate:status --no-interaction
```

`/up` is process liveness only. MySQL readiness is enforced by its container health check and verified through migrations and integration tests.

If startup or a health check fails:

```powershell
docker compose ps
docker compose logs --tail 200 laravel.test mysql
```

## Development commands

```powershell
docker compose up -d --wait --wait-timeout 120
docker compose exec -T laravel.test composer test
docker compose exec -T laravel.test npm run build
docker compose down
```

On Windows bind mounts, tests and frontend builds may be slower inside Docker. CI remains the compatibility authority for both supported runtime pairs.

## Application conventions

- Store timestamps in UTC and present them in `Europe/Amsterdam`.
- Use English UI text, `DD-MM-YYYY` dates, 24-hour time, and Monday-first calendars.
- Use metric measurements and accept comma or point decimal input where quantity parsing is implemented.
- Use the synchronous queue connection for the MVP. Do not rely on database queues until a supervised worker and after-commit behavior are added.

The fixed presentation contract is defined in `config/dinner-decider.php`.

## Full verification

```powershell
docker compose exec -T laravel.test composer validate --strict --no-check-publish
docker compose exec -T laravel.test composer check-platform-reqs
docker compose exec -T laravel.test composer test
docker compose exec -T laravel.test npm ci
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test composer audit --locked --no-interaction
docker compose exec -T laravel.test npm audit --audit-level=high
```

See `docs/architecture.md` for the application architecture and incremental implementation stages.
