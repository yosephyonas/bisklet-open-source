# Production Hardening Notes

## Backend Boundaries

- `App\Services\Rides\RideLifecycleService` owns lock-driven ride start and end transitions.
- `App\Services\Payments\PaymentService` owns payment creation, idempotency, webhook processing, and ride billing status updates.
- Controllers should validate requests, authorize users, and delegate state changes to services.

## Reliability

- Gateway webhooks are authenticated with `GO_GATEWAY_WEBHOOK_TOKEN`.
- Gateway event retries are deduped by `gateway_event_receipts`.
- Payment webhooks are authenticated with HMAC signatures and retained in `payment_webhook_events`.
- Queue connections run `after_commit` by default so jobs do not observe uncommitted database state.
- Redis is the preferred production backing service for cache, locks, and queues.

## Security

- API requests receive request IDs and security headers.
- Auth, lock command, gateway webhook, payment webhook, and general API paths have named rate limiters.
- Laravel-to-gateway command calls receive `X-Gateway-Token` automatically when `GO_GATEWAY_COMMAND_TOKEN` is configured.
- Payment webhook secrets can be global or provider-specific with `CHAPA_WEBHOOK_SECRET` and `TELEBIRR_WEBHOOK_SECRET`.

## Operations

- `GET /api/v1/dashboard/summary` includes lock freshness, low battery count, failed commands, pending revenue, and failed payments.
- `GET /healthz` on the Go gateway reports live device count and event queue pressure.
- Run queue workers separately from web workers:

```bash
php artisan queue:work redis --sleep=1 --tries=3 --timeout=60
```

## Scaling Path

- Scale Laravel web containers horizontally behind a load balancer.
- Scale Laravel queue workers independently by queue depth.
- Keep the Go gateway as the TCP ownership boundary for lock sockets; shard by TCP port, region, or lock fleet if one gateway becomes saturated.
- Use Redis for distributed cache, rate limiting, queue dispatch, and ride-start locks.
- Keep MySQL/PostgreSQL as the source of truth; use the new operational indexes for ride, payment, location, and dashboard queries.
