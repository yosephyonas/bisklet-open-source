# Bisklet Backend

Laravel API for Bisklet dockless bike sharing: riders, bikes, rides, lock telemetry, admin operations, payments, FYDA identity verification, and gateway integrations.

## Production Architecture

- Laravel exposes versioned APIs under `/api/v1`.
- Sanctum protects mobile/admin API traffic.
- The Go gateway owns TCP connections to OMNI locks and exposes a local command API.
- Gateway webhooks are authenticated with `GO_GATEWAY_WEBHOOK_TOKEN` and deduped in `gateway_event_receipts`.
- Laravel commands to the gateway are signed with `GO_GATEWAY_COMMAND_TOKEN`.
- Redis should back cache, locks, and queues in production.
- Ride start/end state transitions are centralized in `App\Services\Rides\RideLifecycleService`.
- Payment creation and webhook reconciliation are centralized in `App\Services\Payments\PaymentService`.

## Required Production Settings

Set these before running outside local development. Assign independent, unpredictable 32+ character secrets from your private deployment environment. The blank values below are intentionally not deployable:

```env
APP_ENV=production
APP_DEBUG=false
CACHE_STORE=redis
QUEUE_CONNECTION=redis
QUEUE_AFTER_COMMIT=true
REDIS_HOST=redis
GO_GATEWAY_BASE_URL=http://gateway:9001
GO_GATEWAY_COMMAND_TOKEN=
GO_GATEWAY_WEBHOOK_TOKEN=
PAYMENT_WEBHOOK_SECRET=
```

Provider-specific payment secrets are supported with `CHAPA_WEBHOOK_SECRET` and `TELEBIRR_WEBHOOK_SECRET`.

## Operations

- Health check: `GET /up`
- Admin summary: `GET /api/v1/dashboard/summary`
- Gateway lock events: `POST /api/v1/lock/events`
- Payment webhooks: `POST /api/v1/payments/webhooks/{provider}`
- Queue worker: `php artisan queue:work redis --sleep=1 --tries=3 --timeout=60`

The dashboard summary includes lock freshness, low battery count, failed lock commands, pending revenue, and failed payments.

Laravel signs gateway commands and verifies gateway webhooks with HMAC-SHA256, timestamps, and nonces. Keep gateway port `9001` private; only Laravel should be able to reach it.

## Local Verification

```bash
composer install
php artisan test
```

To start a local Laravel server, copy `.env.example` to `.env`, generate an application key, run migrations on a development database and use `php artisan serve`. No Docker Compose file is included in this source package.
