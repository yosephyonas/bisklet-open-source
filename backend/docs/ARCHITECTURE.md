# Bisklet Backend Architecture

## Overview
The Bisklet backend is a Laravel 12 API that powers the Flutter rider application and administrative tooling. It provides:

- FYDA (Ethiopian National ID) backed identity verification and authentication.
- Rider and operator management with Sanctum-issued API tokens.
- Bike, station, ride, and payment lifecycle management.
- Analytics endpoints for operational visibility.

The API is stateless and returns JSON responses tailored for consumption by Flutter clients.

## Bounded Contexts

### Identity & Access
- Users authenticate via FYDA verification and receive Sanctum tokens.
- Roles (`rider`, `admin`, `mechanic`) drive authorization gates and policies.
- `FydaVerification` captures verification attempts, responses, and statuses.

### Mobility Assets
- `Bike` entities describe physical bikes including health and pricing metadata.
- `Station` entities describe docking/parking locations (optional for free-floating deployments).
- `MaintenanceLog` tracks interventions on bikes.

### Ride Lifecycle
- `Ride` captures the rental session between rider and bike, including telemetry, pricing, and state transitions.
- `Payment` records the charge for a ride, with hooks for future payment gateway integrations.

## Data Model Summary

- `users`: Core rider/operator records enriched with FYDA attributes and role flags.
- `fyda_verifications`: Audit of every verification call with FYDA.
- `stations`: Optional docking locations with geo coordinates and capacity.
- `bikes`: Inventory of bikes, linked to stations, status, and pricing metadata.
- `maintenance_logs`: Free-form logs tied to bikes for up-time tracking.
- `rides`: Represents an active or completed ride with lifecycle timestamps.
- `payments`: Monetary transactions linked to rides.

## Authentication Flow

1. The Flutter client requests `POST /auth/fyda/authorize` to create a Fayda OIDC authorization session.
2. The client opens the returned Fayda authorization URL. Fayda redirects back to `/auth/fyda/callback` with an authorization code.
3. The backend exchanges the code with PKCE and a signed client assertion, fetches Fayda userinfo, and writes an audit record to `fyda_verifications`.
4. Verified identities are upserted into `users`, and a Sanctum token is cached against the OIDC state.
5. The Flutter client polls `GET /auth/fyda/result/{state}` to receive the token and user profile.
6. Subsequent API calls attach the bearer token. Token revocation endpoints (`/auth/logout`, `/auth/logout-all`) support session management.

## External Integrations

- **FYDA**: Configured via `config/fyda.php` and environment variables. Uses Fayda/eSignet OIDC Authorization Code + PKCE.
- **Notifications (future)**: Notification channels are abstracted behind Laravel events and queued jobs.

## API Surface (High Level)

- `POST /auth/fyda/authorize` — Start Fayda OIDC authorization.
- `GET /auth/fyda/callback` — Fayda redirect callback and token exchange.
- `GET /auth/fyda/result/{state}` — Poll completed authorization result.
- `POST /auth/logout` / `POST /auth/logout-all` — Token revocation.
- `GET /auth/me` — Session status & profile details.
- `GET /bikes`, `POST /bikes`, `PATCH /bikes/{id}`, `DELETE /bikes/{id}` — Bike management.
- `GET /stations`, `POST /stations`, ... — Station CRUD (admin only).
- `POST /rides` (start), `POST /rides/{ride}/complete`, `GET /rides` — Ride lifecycle.
- `POST /rides/{ride}/payments` — Record ride payment.
- `GET /dashboard/summary` — Operational metrics for admin dashboards.

## Testing Strategy

- Feature tests cover authentication flows, bike CRUD, and ride lifecycle.
- HTTP calls to Fayda token/userinfo endpoints are faked in tests.
- Seeders provide baseline admin and sample bikes for local development.

## Deployment Notes

- App ships with Sail & Docker support for local use; production deploys should bake Sanctum's config and queue workers.
- Queue workers handle FYDA retry jobs and asynchronous notifications.


