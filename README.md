# Bisklet | Open Source Bike-Sharing Technology

Bisklet is a mobility software project based in Addis Ababa, Ethiopia. It explores accessible, reusable technology for bike-sharing systems.

The codebase includes a Flutter rider application, a Laravel API, and a Go TCP gateway. The backend has been integrated with real smart bike locks. This project does not claim a public fleet launch, third-party adoption, download counts or GitHub stars.

## Repository structure

| Directory | Purpose |
| --- | --- |
| `frontend/` | Flutter rider app, maps, ride history and UI |
| `backend/` | Laravel API, administration, payments and test fixtures |
| `gateway/` | Go gateway for IoT integration and automated tests |

## Run the checks

**Flutter:** Install Flutter with Dart 3.9.2 or later, then run `cd frontend && flutter pub get && flutter analyze --no-fatal-infos --no-fatal-warnings && flutter test`.

**Laravel:** Install PHP 8.2+ and Composer, then run `cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan test`.

**Go:** Install the version specified in `gateway/go.mod`, then run `cd gateway && go test ./... && go vet ./...`.

CI in `.github/workflows/ci.yml` tests all components on pushes and pull requests.

## Safe local development

Flutter defaults to a loopback API and requires HTTPS for non-local servers. Do not distribute mobile apps with private mapping tokens or production credentials.

The Laravel demo seeder refuses to run in production. For local test logins, set `BISKLET_DEMO_PASSWORD` to a strong development-only password; otherwise it generates a disposable random password.

Never connect the sample software to production smart-lock infrastructure without an independent security review, authorization and proper isolation. No private Git history, customer data or production secrets are intentionally included. Four unverified third-party marketing photographs were replaced with placeholders.

## Evidence of local need

ITDP reports more than 241 km of cycle tracks developed across eight Addis Ababa corridors as of July 2026. Separately, the bike-share operator Addis Bike reported more than 51,000 trips through October 2026. These statistics are not Bisklet usage or open-source community adoption. See [impact and adoption](docs/IMPACT_AND_ADOPTION.md).

## License, security and contribution

Original Bisklet source is provided under [Apache License 2.0](LICENSE). Dependencies keep their own licenses; see [third-party notices](THIRD_PARTY.md). Contributions are welcome under [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities according to [SECURITY.md](SECURITY.md).

This repository has clean public history. Do not merge old private repository history into it.
