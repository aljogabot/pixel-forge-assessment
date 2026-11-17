# Prompt Edit — Image Generation Demo

A Laravel, Vue, and Inertia application built for the Prompt Edit assessment.
Image generation is provided by PixelForge.

## Live demo

https://184.33.136.127

Register an account to use the application.

## Features

- Prompt-based image generation with size and quality settings
- Up to four reference image URLs
- Price estimation before submission
- Paginated generation history with status updates and notifications
- Signed webhook verification and duplicate-event handling
- Background submission, retries, and scheduled recovery
- Actual usage billing with a 25% markup; failed generations are not charged

## Technology

- PHP 8.5 and Laravel 13
- Vue 3, TypeScript, Inertia 3, and Tailwind CSS 4
- PostgreSQL 17
- Database-backed queues, cache, and sessions
- Docker Compose and Nginx

## Local setup

Prerequisites: PHP 8.5 with BCMath and PostgreSQL extensions, Composer,
Node.js 22, npm, and Docker Compose.

```bash
git clone https://github.com/aljogabot/pixel-forge-assessment.git
cd pixel-forge-assessment
cp .env.example .env
composer install
npm ci
php artisan key:generate
```

Configure `.env`:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=prompt_edit_demo
DB_USERNAME=prompt_edit
DB_PASSWORD=your-local-password

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

GENERATION_PROVIDER=pixel_forge
PIXELFORGE_API_KEY=your-api-key
PIXELFORGE_WEBHOOK_SECRET=your-webhook-secret
```

Start PostgreSQL and run migrations:

```bash
docker compose up -d postgres
php artisan migrate
```

Run these processes in separate terminals:

```bash
php artisan serve
npm run dev
php artisan queue:work --tries=0 --timeout=30
php artisan schedule:work
```

Open http://localhost:8000.

For local webhooks, expose the Laravel server through an HTTPS tunnel and
register this endpoint in PixelForge:

```text
https://your-tunnel-host/webhooks/pixelforge
```

The webhook signing secret must match the value in `.env`.

## Architecture

Domain code is grouped under `app/Modules` with the `Modules` namespace.
Controllers handle HTTP requests, actions coordinate business operations,
and services implement reusable behavior.

Provider contracts separate the generation workflow from the PixelForge
adapter. Provider implementations are resolved through configuration and
the Laravel service container.

Submission runs in a background job using a persistent idempotency key.
Webhook processing uses unique event identifiers, database transactions,
and row locks to prevent duplicate settlement and protect terminal states.

Scheduled recovery tracks eligible unfinished generations separately from
generation records. Jobs stopped with `submission_job_failed` require
manual investigation.

## Billing

Estimates use estimated token usage. Successful generations are settled
using the provider's reported usage and generation-specific rates.

Amounts are calculated using BCMath decimal arithmetic and stored with
12 decimal places:

```text
Provider cost = sum(token count × rate per million / 1,000,000)
Customer charge = provider cost × 1.25
```

Failed generations have zero provider cost and zero customer charge.

## Verification

```bash
php artisan test --compact
npm run types:check
npm run build
```

Tests cover billing, webhook signatures and duplicate events, terminal
state protection, dashboard pagination, recovery, request limiting, and
provider retry decisions.

Two starter-kit tests are skipped because two-factor authentication is
disabled.

## Deployment

The demo runs on one AWS Lightsail instance with Docker Compose services
for Laravel/PHP-FPM, Nginx, a queue worker, a scheduler, and PostgreSQL.

Production configuration is in `compose.production.yaml`.
Secrets are supplied through an uncommitted `.env.production` file.

HTTPS uses a Let's Encrypt IP certificate, with automatic renewal and an
Nginx reload hook.

## Limitations

- This is a single-server assessment deployment, without high availability.
- Generated image URLs are supplied by PixelForge and expire; images are
  not archived by this application.
- Email uses the log transport in the demo; password-reset emails are not
  delivered.
- Switching providers while work is outstanding requires operational
  coordination.
- Automated tests do not establish production throughput or prove
  PostgreSQL locking behavior under concurrent load.
